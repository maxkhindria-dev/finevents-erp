<?php

/*
| PERMISSION LAYER 3: what MySQL itself allows each login to do.
|
| These tests connect as the real logins, so they only run when the database
| has been prepared with real accounts (FINEVENTS_PRIVILEGE_TESTS=1, done in CI):
|   1. php artisan migrate:fresh --database=mysql_migrator
|   2. php artisan finevents:db:privileges --apply
| Every "refused" below is refused by MySQL, not by the app's own code.
*/

use App\Access\AccessContext;
use App\Audit\AuditChainVerifier;
use App\Audit\AuditLogger;
use App\Models\Entity;
use App\Models\Project;
use App\Models\ProjectFinancial;
use Database\Factories\EntityFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    if (! getenv('FINEVENTS_PRIVILEGE_TESTS') || DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Needs MySQL with real app/sensitive/migrator logins (runs in CI).');
    }

    config(['finevents.sensitive_connection' => 'mysql_sensitive']);
});

function refusedByMysql(callable $attempt): void
{
    expect($attempt)->toThrow(QueryException::class, 'denied');
}

// --- The app login and the audit log ------------------------------------------

it('lets the app login add audit entries and seal them', function () {
    app(AuditLogger::class)->record('privilege_test.ran');

    expect(DB::table('audit_events')->where('action', 'privilege_test.ran')->exists())->toBeTrue()
        ->and(app(AuditChainVerifier::class)->firstProblem())->toBeNull();
});

it('refuses the app login any way of changing or removing audit entries', function (string $sql) {
    refusedByMysql(fn () => DB::statement($sql));
})->with([
    'edit' => ["UPDATE audit_events SET action = 'nothing.happened'"],
    'delete' => ['DELETE FROM audit_events'],
    'empty the table' => ['TRUNCATE TABLE audit_events'],
    'drop the table' => ['DROP TABLE audit_events'],
    'remove the protection' => ['DROP TRIGGER audit_events_no_update'],
    'rewrite the archive record' => ['DELETE FROM audit_archives'],
]);

// --- Sensitive data ------------------------------------------------------------

it('refuses the app login any access to sensitive tables', function (string $sql) {
    refusedByMysql(fn () => DB::statement($sql));
})->with([
    'read budgets' => ['SELECT * FROM project_financials'],
    'change budgets' => ["UPDATE project_financials SET budget = 'x'"],
]);

it('refuses the sensitive login access beyond what it needs', function (string $sql) {
    refusedByMysql(fn () => DB::connection('mysql_sensitive')->statement($sql));
})->with([
    'read people' => ['SELECT * FROM people'],
    'read clients' => ['SELECT * FROM clients'],
    'write the audit log' => ["INSERT INTO audit_events (seq, occurred_at, actor_type, action, prev_hash, hash) VALUES (999999, 'x', 'system', 'x', 'x', 'x')"],
    'change projects' => ["UPDATE projects SET name = 'x'"],
]);

it('stores and reads a budget end to end through the two logins', function () {
    $financial = app(AccessContext::class)->runAsSystem(function () {
        $entity = Entity::query()->firstOrCreate(['code' => 'FR'], EntityFactory::ENTITIES['FR']);
        // These tests don't roll back, so pick a project code not used by an earlier run.
        $code = sprintf('FR-2099-%03d', DB::table('projects')->count() + 1);
        $project = Project::factory()->in($entity)->create(['code' => $code]);   // app login

        return ProjectFinancial::factory()->create(['project_id' => $project->id, 'budget' => '42000']); // sensitive login
    });

    $reloaded = app(AccessContext::class)->runAsSystem(fn () => ProjectFinancial::query()->find($financial->id));

    expect($financial->getConnectionName())->toBe('mysql_sensitive')
        ->and($reloaded?->budget)->toBe('42000');
});

// --- Structure changes --------------------------------------------------------

it('refuses the app login any change to the database structure', function (string $sql) {
    refusedByMysql(fn () => DB::statement($sql));
})->with([
    'create a table' => ['CREATE TABLE sneaky (id INT)'],
    'alter a table' => ['ALTER TABLE people ADD COLUMN sneaky INT'],
    'grant itself more' => ['GRANT SELECT ON project_financials TO CURRENT_USER()'],
]);
