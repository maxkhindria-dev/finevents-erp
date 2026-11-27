<?php

namespace App\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Logs every create, update and delete of a model to the audit log, with the
 * before and after values of the fields that changed.
 *
 * Encrypted and hidden fields are never copied into the log: the log only
 * records that they changed ("[encrypted]" / "[hidden]").
 */
trait Audited
{
    public static function bootAudited(): void
    {
        static::created(function (Model $model): void {
            self::writeAudit($model, 'created', null, self::auditableValues($model, array_keys($model->getAttributes())));
        });

        static::updated(function (Model $model): void {
            $changed = array_diff(array_keys($model->getChanges()), self::auditIgnoredFields());

            if ($changed === []) {
                return;
            }

            $before = [];
            foreach ($changed as $field) {
                $before[$field] = $model->getRawOriginal($field);
            }

            self::writeAudit($model, 'updated', self::mask($model, $before), self::auditableValues($model, $changed));
        });

        static::deleted(function (Model $model): void {
            self::writeAudit($model, 'deleted', self::auditableValues($model, array_keys($model->getAttributes())), null);
        });
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private static function writeAudit(Model $model, string $event, ?array $before, ?array $after): void
    {
        // Read raw attributes: not every model has these columns, and strict
        // mode rightly refuses to read a column that doesn't exist.
        $attributes = $model->getAttributes();
        $entityId = $attributes['entity_id'] ?? $attributes['primary_entity_id'] ?? null;

        app(AuditLogger::class)->record(
            action: self::auditName($model).'.'.$event,
            targetType: self::auditName($model),
            targetId: (int) $model->getKey(),
            entityId: $entityId === null ? null : (int) $entityId,
            before: $before,
            after: $after,
        );
    }

    /**
     * @param  list<string>|array<int, string>  $fields
     * @return array<string, mixed>
     */
    private static function auditableValues(Model $model, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            if (in_array($field, self::auditIgnoredFields(), true)) {
                continue;
            }
            $values[$field] = $model->getAttributes()[$field] ?? null; // raw stored value
        }

        return self::mask($model, $values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function mask(Model $model, array $values): array
    {
        foreach (array_keys($values) as $field) {
            $cast = $model->getCasts()[$field] ?? null;

            if (is_string($cast) && str_starts_with($cast, 'encrypted')) {
                $values[$field] = '[encrypted]';
            } elseif (in_array($field, $model->getHidden(), true)) {
                $values[$field] = '[hidden]';
            }
        }

        return $values;
    }

    /** @return list<string> */
    private static function auditIgnoredFields(): array
    {
        return ['created_at', 'updated_at', 'last_login_at', 'owner_slot'];
    }

    /** e.g. App\Models\ProjectFinancial → project_financial */
    private static function auditName(Model $model): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename($model)));
    }
}
