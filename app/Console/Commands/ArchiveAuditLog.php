<?php

namespace App\Console\Commands;

use App\Audit\AuditChainVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Copies audit entries not yet archived to the archive disk, as one JSON-lines
 * file per run. In production the disk is write-once EU storage, so archived
 * entries can't be changed or deleted by anyone until their retention date,
 * including whoever controls the database.
 */
class ArchiveAuditLog extends Command
{
    protected $signature = 'finevents:audit:archive';

    protected $description = 'Copy new audit log entries to the write-once archive';

    public function handle(AuditChainVerifier $verifier): int
    {
        // Never archive a chain that is already broken.
        if (($problem = $verifier->firstProblem()) !== null) {
            $this->error("Not archiving: audit log integrity check failed: {$problem}");

            return self::FAILURE;
        }

        $fromSeq = (int) DB::table('audit_archives')->max('to_seq') + 1;
        $rows = DB::table('audit_events')->where('seq', '>=', $fromSeq)->orderBy('seq')->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing new to archive.');

            return self::SUCCESS;
        }

        $toSeq = (int) $rows->last()->seq;
        $lastHash = (string) $rows->last()->hash;
        $contents = $rows->map(fn ($row) => json_encode((array) $row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))->implode("\n")."\n";

        $disk = (string) config('finevents.audit_archive_disk');
        $path = sprintf('audit/%s/audit-%010d-%010d.jsonl', now('UTC')->format('Y/m/d'), $fromSeq, $toSeq);

        if (Storage::disk($disk)->exists($path)) {
            $this->error("Archive file {$path} already exists; refusing to overwrite.");

            return self::FAILURE;
        }

        Storage::disk($disk)->put($path, $contents);

        DB::table('audit_archives')->insert([
            'from_seq' => $fromSeq,
            'to_seq' => $toSeq,
            'last_hash' => $lastHash,
            'disk' => $disk,
            'path' => $path,
            'file_sha256' => hash('sha256', $contents),
        ]);

        $this->info("Archived entries {$fromSeq} to {$toSeq} to {$disk}:{$path}.");

        return self::SUCCESS;
    }
}
