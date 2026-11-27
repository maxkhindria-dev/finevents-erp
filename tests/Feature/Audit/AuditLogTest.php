<?php

/*
| The audit log (CLAUDE.md §5.5): every change recorded, nothing sensitive
| copied into it, nobody able to change it, and any tampering detected.
*/

use App\Access\AccessContext;
use App\Audit\AuditChainVerifier;
use App\Audit\AuditLogger;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectFinancial;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

function asSystem(callable $callback): mixed
{
    return app(AccessContext::class)->runAsSystem($callback);
}

/** @return list<object> */
function auditRows(): array
{
    return DB::table('audit_events')->orderBy('seq')->get()->all();
}

/**
 * Simulates someone with full database rights bypassing the append-only
 * triggers. On MySQL this also commits the test's data, so the next test
 * rebuilds the database from scratch (triggers included).
 */
function dropAuditTriggers(): void
{
    DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_update');
    DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_delete');
    RefreshDatabaseState::$migrated = false;
}

beforeEach(function () {
    $this->france = asSystem(fn () => Entity::factory()->code('FR')->create());
    $this->admin = Person::factory()->admin()->in($this->france)->create();
});

// --- What gets recorded -------------------------------------------------------

it('records who created a record, what it contained, and in which entity', function () {
    $project = app(AccessContext::class)->runAs($this->admin, fn () => Project::factory()->in($this->france)->create(['name' => 'Investor Forum 2027']));

    $event = collect(auditRows())->firstWhere('action', 'project.created');

    expect($event)->not->toBeNull()
        ->and($event->actor_type)->toBe('person')
        ->and((int) $event->actor_person_id)->toBe($this->admin->id)
        ->and((int) $event->target_id)->toBe($project->id)
        ->and((int) $event->entity_id)->toBe($this->france->id)
        ->and(json_decode($event->after, true)['name'])->toBe('Investor Forum 2027');
});

it('records the before and after values of an edit', function () {
    $project = asSystem(fn () => Project::factory()->in($this->france)->create(['venue' => 'Paris']));
    asSystem(fn () => $project->update(['venue' => 'London']));

    $event = collect(auditRows())->firstWhere('action', 'project.updated');

    expect(json_decode($event->before, true))->toBe(['venue' => 'Paris'])
        ->and(json_decode($event->after, true))->toBe(['venue' => 'London'])
        ->and($event->actor_type)->toBe('system');
});

it('records role changes on people', function () {
    $user = Person::factory()->in($this->france)->create();
    $user->role = Role::Admin;
    asSystem(fn () => $user->save());

    $event = collect(auditRows())->firstWhere('action', 'person.updated');

    expect(json_decode($event->before, true))->toBe(['role' => 'user'])
        ->and(json_decode($event->after, true))->toBe(['role' => 'admin']);
});

it('never copies encrypted values (budgets) into the audit log', function () {
    $financial = asSystem(fn () => ProjectFinancial::factory()->create(['budget' => '987654']));
    asSystem(fn () => $financial->update(['budget' => '123456']));

    $everything = json_encode(auditRows());
    $updated = collect(auditRows())->firstWhere('action', 'project_financial.updated');

    expect($everything)->not->toContain('987654')->not->toContain('123456')
        ->and(json_decode($updated->after, true))->toBe(['budget' => '[encrypted]']);
});

it('never copies hidden values (Google account IDs) into the audit log', function () {
    Person::factory()->in($this->france)->create(['google_id' => 'google-secret-123']);

    expect(json_encode(auditRows()))->not->toContain('google-secret-123');
});

it('does not log saves where nothing meaningful changed', function () {
    $project = asSystem(fn () => Project::factory()->in($this->france)->create());
    $before = count(auditRows());

    asSystem(fn () => $project->touch());

    expect(count(auditRows()))->toBe($before);
});

// --- Nobody can change it ----------------------------------------------------

it('refuses to edit or delete audit entries at the database level', function (string $operation) {
    asSystem(fn () => Project::factory()->in($this->france)->create());

    $attempt = $operation === 'update'
        ? fn () => DB::table('audit_events')->update(['action' => 'nothing.happened'])
        : fn () => DB::table('audit_events')->delete();

    expect($attempt)->toThrow(QueryException::class, 'append-only');
})->with(['update', 'delete']);

it('refuses to create, edit or delete audit entries through the model', function () {
    asSystem(fn () => Project::factory()->in($this->france)->create());
    $event = asSystem(fn () => AuditEvent::query()->firstOrFail());

    expect(fn () => $event->forceFill(['action' => 'x'])->save())->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class)
        ->and(fn () => (new AuditEvent)->forceFill(['action' => 'x'])->save())->toThrow(LogicException::class);
});

it('lets only the Owner and Super Admins read the audit log, and nobody change it', function () {
    asSystem(fn () => Project::factory()->in($this->france)->create());
    $event = asSystem(fn () => AuditEvent::query()->firstOrFail());

    $owner = Person::factory()->owner()->in($this->france)->create();
    $superAdmin = Person::factory()->superAdmin()->in($this->france)->create();
    $user = Person::factory()->in($this->france)->create();

    foreach ([$owner, $superAdmin] as $leader) {
        expect(app(AccessContext::class)->runAs($leader, fn () => AuditEvent::query()->count()))->toBeGreaterThan(0)
            ->and(Gate::forUser($leader)->allows('view', $event))->toBeTrue()
            ->and(Gate::forUser($leader)->allows('update', $event))->toBeFalse()
            ->and(Gate::forUser($leader)->allows('delete', $event))->toBeFalse();
    }

    foreach ([$this->admin, $user] as $other) {
        expect(app(AccessContext::class)->runAs($other, fn () => AuditEvent::query()->count()))->toBe(0)
            ->and(Gate::forUser($other)->allows('view', $event))->toBeFalse();
    }
});

// --- Tampering is detected ---------------------------------------------------

it('confirms an untouched chain is intact', function () {
    asSystem(fn () => Project::factory()->count(3)->in($this->france)->create());

    expect(app(AuditChainVerifier::class)->firstProblem())->toBeNull();
    $this->artisan('finevents:audit:verify')->expectsOutputToContain('intact')->assertSuccessful();
});

it('detects an entry whose contents were altered', function () {
    asSystem(fn () => Project::factory()->count(3)->in($this->france)->create());
    dropAuditTriggers();
    DB::table('audit_events')->where('seq', 2)->update(['actor_person_id' => 999]);

    expect(app(AuditChainVerifier::class)->firstProblem())->toBe('Entry 2 has been altered: its seal does not match its contents.');
    $this->artisan('finevents:audit:verify')->assertFailed();
});

it('detects an entry that was removed from the middle', function () {
    asSystem(fn () => Project::factory()->count(3)->in($this->france)->create());
    dropAuditTriggers();
    DB::table('audit_events')->where('seq', 2)->delete();

    expect(app(AuditChainVerifier::class)->firstProblem())->toContain('Entry 2 is missing');
});

it('detects the most recent entries being removed', function () {
    asSystem(fn () => Project::factory()->count(3)->in($this->france)->create());
    dropAuditTriggers();
    $last = DB::table('audit_events')->max('seq');
    DB::table('audit_events')->where('seq', $last)->delete();

    expect(app(AuditChainVerifier::class)->firstProblem())->toContain('latest entries are missing');
});

it('detects an entry re-sealed without the secret key', function () {
    asSystem(fn () => Project::factory()->count(2)->in($this->france)->create());
    dropAuditTriggers();

    // An attacker rewrites entry 1 and re-seals it with a key they guessed.
    $row = (array) DB::table('audit_events')->where('seq', 1)->first();
    $row['action'] = 'nothing.happened';
    DB::table('audit_events')->where('seq', 1)->update(['action' => $row['action'], 'hash' => AuditLogger::seal($row, 'wrong-key')]);

    expect(app(AuditChainVerifier::class)->firstProblem())->toBe('Entry 1 has been altered: its seal does not match its contents.');
});

it('refuses to write unsealed entries in production without AUDIT_CHAIN_KEY', function () {
    $originalEnv = app()->environment();
    app()['env'] = 'production';

    try {
        expect(fn () => app(AuditLogger::class)->record('test.event'))
            ->toThrow(RuntimeException::class, 'AUDIT_CHAIN_KEY is missing');
    } finally {
        app()['env'] = $originalEnv;
    }
});

// --- Nightly archive -----------------------------------------------------------

it('archives new entries to the archive disk, once, with a checksum', function () {
    Storage::fake('local');
    asSystem(fn () => Project::factory()->count(2)->in($this->france)->create());
    $firstBatch = (int) DB::table('audit_events')->max('seq');

    $this->artisan('finevents:audit:archive')->assertSuccessful();
    $archive = DB::table('audit_archives')->first();
    $contents = Storage::disk('local')->get($archive->path);

    expect((int) $archive->from_seq)->toBe(1)
        ->and((int) $archive->to_seq)->toBe($firstBatch)
        ->and(hash('sha256', (string) $contents))->toBe($archive->file_sha256)
        ->and(substr_count((string) $contents, "\n"))->toBe($firstBatch);

    // A second run only archives what is new.
    $this->artisan('finevents:audit:archive')->expectsOutputToContain('Nothing new')->assertSuccessful();
    asSystem(fn () => Project::factory()->in($this->france)->create());
    $this->artisan('finevents:audit:archive')->assertSuccessful();

    expect((int) DB::table('audit_archives')->orderByDesc('id')->value('from_seq'))->toBe($firstBatch + 1);
});

it('refuses to archive a chain that has been tampered with', function () {
    Storage::fake('local');
    asSystem(fn () => Project::factory()->count(2)->in($this->france)->create());
    dropAuditTriggers();
    DB::table('audit_events')->where('seq', 1)->update(['action' => 'nothing.happened']);

    $this->artisan('finevents:audit:archive')->expectsOutputToContain('integrity check failed')->assertFailed();
    expect(DB::table('audit_archives')->count())->toBe(0);
});
