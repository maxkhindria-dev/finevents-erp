<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Permission layer 3: what each MySQL login may do, table by table.
 *
 *  - App login (DB_USERNAME): normal read/write on ordinary tables; on the
 *    audit log only SELECT and INSERT (no UPDATE, DELETE, DROP or TRUNCATE);
 *    NO access at all to sensitive tables.
 *  - Sensitive login (DB_SENSITIVE_USERNAME): read/write on sensitive tables,
 *    plus read-only on the few tables its permission checks join to.
 *  - Migrator login (DB_MIGRATOR_USERNAME): changes the database structure and
 *    runs this command. The running app never holds these credentials.
 *
 * Run after every migration (new tables need their grants):
 *   php artisan migrate --database=mysql_migrator --force
 *   php artisan finevents:db:privileges --apply
 */
class DatabasePrivileges extends Command
{
    protected $signature = 'finevents:db:privileges
        {--apply : Run the statements (otherwise they are only printed)}
        {--connection=mysql_migrator : Connection with permission to grant}';

    protected $description = 'Print or apply the MySQL permissions for the app and sensitive logins';

    /** Tables holding sensitive values. The app login gets no access to these. */
    public const SENSITIVE_TABLES = ['project_financials'];

    /** Append-only tables: the app login may only read and add rows. */
    public const APPEND_ONLY_TABLES = ['audit_events', 'audit_archives'];

    /** Tables the sensitive login may read for its own permission checks. */
    public const SENSITIVE_LOGIN_READS = ['projects', 'project_members', 'entities', 'sensitive_grants'];

    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $statements = $this->statements($connection);

        if (! $this->option('apply')) {
            foreach ($statements as $sql) {
                $this->line($sql.';');
            }

            return self::SUCCESS;
        }

        foreach ($statements as $sql) {
            DB::connection($connection)->statement($sql);
        }

        $this->info(count($statements).' permission statements applied.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    public function statements(string $connection): array
    {
        $db = DB::connection($connection)->getDatabaseName();
        $app = $this->account('database.connections.mysql.username');
        $sensitive = $this->account('database.connections.mysql_sensitive.username');
        $tables = array_values(array_filter(
            Schema::connection($connection)->getTableListing(schema: $db, schemaQualified: false),
            fn (string $table) => $table !== 'migrations',
        ));

        $q = fn (string $name) => '`'.str_replace('`', '``', $name).'`';
        $on = fn (string $table) => $q($db).'.'.$q($table);

        $sql = [
            "REVOKE ALL PRIVILEGES, GRANT OPTION FROM {$app}",
            "REVOKE ALL PRIVILEGES, GRANT OPTION FROM {$sensitive}",
        ];

        foreach ($tables as $table) {
            if (in_array($table, self::SENSITIVE_TABLES, true)) {
                $sql[] = "GRANT SELECT, INSERT, UPDATE, DELETE ON {$on($table)} TO {$sensitive}";
            } elseif (in_array($table, self::APPEND_ONLY_TABLES, true)) {
                $sql[] = "GRANT SELECT, INSERT ON {$on($table)} TO {$app}";
            } elseif ($table === 'audit_chain') {
                $sql[] = "GRANT SELECT, UPDATE ON {$on($table)} TO {$app}"; // moves the chain head only
            } else {
                $sql[] = "GRANT SELECT, INSERT, UPDATE, DELETE ON {$on($table)} TO {$app}";
            }

            if (in_array($table, self::SENSITIVE_LOGIN_READS, true)) {
                $sql[] = "GRANT SELECT ON {$on($table)} TO {$sensitive}";
            }
        }

        return $sql;
    }

    /** 'user'@'host' for a configured login. */
    private function account(string $configKey): string
    {
        $user = config($configKey);

        if (! is_string($user) || $user === '') {
            throw new RuntimeException("{$configKey} is not set.");
        }

        $host = (string) config('finevents.db_account_host', '%');

        return "'".str_replace("'", "''", $user)."'@'".str_replace("'", "''", $host)."'";
    }
}
