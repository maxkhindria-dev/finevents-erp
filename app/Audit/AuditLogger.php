<?php

namespace App\Audit;

use App\Access\AccessContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Writes the audit log. Every entry is sealed to the one before it:
 *
 *   hash = HMAC-SHA256(key, previous hash + this entry's exact contents)
 *
 * Changing, removing or reordering any entry breaks every seal after it, and
 * the seals can't be recomputed without the key, which lives outside the
 * database (AUDIT_CHAIN_KEY). The chain head row is locked while writing so
 * simultaneous events can't fork the chain.
 */
class AuditLogger
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    public function __construct(
        private readonly AccessContext $context,
        private readonly Application $app,
    ) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        $key = $this->key();
        $actor = $this->context->viewer();
        $request = $this->app->runningInConsole() ? null : request();

        $row = [
            'occurred_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
            'actor_type' => $actor === null ? 'system' : 'person',
            'actor_person_id' => $actor?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'entity_id' => $entityId,
            'before' => $before === null ? null : self::canonicalJson($before),
            'after' => $after === null ? null : self::canonicalJson($after),
            'ip' => $request?->ip(),
            'user_agent' => $request === null ? null : mb_substr((string) $request->userAgent(), 0, 255),
            'country' => null, // filled in by sign-in geolocation (step 4)
        ];

        DB::transaction(function () use ($row, $key): void {
            $head = DB::table('audit_chain')->where('id', 1)->lockForUpdate()->first();

            if ($head === null) {
                throw new RuntimeException('Audit chain head is missing; refusing to write an unsealed audit entry.');
            }

            $row['seq'] = (int) $head->last_seq + 1;
            $row['prev_hash'] = (string) $head->last_hash;
            $row['hash'] = self::seal($row, $key);

            DB::table('audit_events')->insert($row);
            DB::table('audit_chain')->where('id', 1)->update(['last_seq' => $row['seq'], 'last_hash' => $row['hash']]);
        });
    }

    /**
     * The exact bytes that are sealed. Verification recomputes this from the
     * stored row, so every field is stored as plain text, exactly as sealed.
     *
     * @param  array<string, mixed>  $row
     */
    public static function seal(array $row, string $key): string
    {
        $fields = [
            'seq', 'occurred_at', 'actor_type', 'actor_person_id', 'action', 'target_type', 'target_id',
            'entity_id', 'before', 'after', 'ip', 'user_agent', 'country',
        ];

        $payload = [];
        foreach ($fields as $field) {
            $value = $row[$field] ?? null;
            $payload[$field] = $value === null ? null : (string) $value;
        }

        return hash_hmac('sha256', $row['prev_hash']."\n".json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $key);
    }

    /**
     * JSON with keys sorted at every level, so the same data always gives the same text.
     *
     * @param  array<mixed>  $data
     */
    public static function canonicalJson(array $data): string
    {
        return json_encode(self::sortKeys($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function key(): string
    {
        $key = config('finevents.audit_chain_key');

        if (is_string($key) && $key !== '') {
            return str_starts_with($key, 'base64:') ? (string) base64_decode(substr($key, 7), true) : $key;
        }

        if ($this->app->isProduction()) {
            throw new RuntimeException('AUDIT_CHAIN_KEY is missing. Refusing to write unsealed audit entries in production.');
        }

        return (string) config('app.key'); // local development and tests only
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function sortKeys(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }

        foreach ($data as $k => $value) {
            if (is_array($value)) {
                $data[$k] = self::sortKeys($value);
            }
        }

        return $data;
    }
}
