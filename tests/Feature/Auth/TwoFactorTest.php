<?php

/*
| Second factor (CLAUDE.md §5.1): required for everyone, every session.
*/

use App\Access\AccessContext;
use App\Models\MfaCredential;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/AuthHelpers.php';

beforeEach(function () {
    config(['finevents.workspace_domain' => TEST_DOMAIN]);
    $this->withoutVite();
});

it('blocks the app until the second factor is set up', function () {
    invitedPerson();
    googleReturns();
    $this->get('/auth/google/callback');

    $this->get('/home')->assertRedirect(route('mfa.setup'));
    $this->get('/mfa/setup')->assertOk()->assertSee('<svg', false)->assertSee('6-digit code');
});

it('sets up an authenticator app, shows recovery codes once, then opens the app', function () {
    $person = invitedPerson();
    googleReturns();
    $this->get('/auth/google/callback');
    $this->get('/mfa/setup');
    $secret = (string) session('mfa_pending_secret');

    $this->post('/mfa/setup', ['code' => '000000'])->assertSessionHasErrors('code');

    $this->post('/mfa/setup', ['code' => currentCode($secret)])
        ->assertOk()
        ->assertSee('Save these recovery codes now');

    $this->get('/home')->assertOk()->assertSee('Welcome');

    // Stored encrypted: the secret never appears in the database as typed.
    $stored = DB::table('mfa_credentials')->where('person_id', $person->id)->first();
    expect($stored->secret)->not->toContain($secret)
        ->and($stored->recovery_code_hashes)->not->toContain('-');
});

it('asks for a code on every new sign-in once set up', function () {
    $person = invitedPerson();
    $secret = signInFully($person, $this);
    $this->post('/sign-out');

    googleReturns($person->email, 'google-'.$person->id);
    $this->get('/auth/google/callback');

    $this->get('/home')->assertRedirect(route('mfa.challenge'));
    $this->post('/mfa', ['code' => nextCode($secret)])->assertSessionHasNoErrors()->assertRedirect(route('home'));
    $this->get('/home')->assertOk();
});

it('refuses a wrong code and logs the failure', function () {
    $person = invitedPerson();
    signInFully($person, $this);
    session()->forget('mfa_verified_at');

    $this->post('/mfa', ['code' => '123456'])->assertSessionHasErrors('code');
    $this->get('/home')->assertRedirect(route('mfa.challenge'));
    expect(DB::table('audit_events')->where('action', 'auth.mfa_failed')->exists())->toBeTrue();
});

it('never accepts the same authenticator code twice', function () {
    $person = invitedPerson();
    $secret = signInFully($person, $this);   // uses the current code
    session()->forget('mfa_verified_at');

    $this->post('/mfa', ['code' => currentCode($secret)])->assertSessionHasErrors('code');
});

it('accepts each recovery code exactly once', function () {
    $person = invitedPerson();
    googleReturns($person->email, 'google-'.$person->id);
    $this->get('/auth/google/callback');
    $this->get('/mfa/setup');
    $secret = (string) session('mfa_pending_secret');
    $codes = $this->post('/mfa/setup', ['code' => currentCode($secret)])->viewData('codes');

    session()->forget('mfa_verified_at');
    $this->post('/mfa', ['code' => $codes[0]])->assertSessionHasNoErrors()->assertRedirect(route('home'));
    expect(DB::table('audit_events')->where('action', 'auth.recovery_code_used')->exists())->toBeTrue();

    session()->forget('mfa_verified_at');
    $this->post('/mfa', ['code' => $codes[0]])->assertSessionHasErrors('code');

    $left = app(AccessContext::class)->runAsSystem(fn () => MfaCredential::query()->where('person_id', $person->id)->first());
    expect($left?->recovery_code_hashes)->toHaveCount(7);
});

it('throttles code guessing to 5 attempts a minute', function () {
    $person = invitedPerson();
    signInFully($person, $this);
    session()->forget('mfa_verified_at');

    foreach (range(1, 5) as $i) {
        $this->post('/mfa', ['code' => '000000']);
    }

    $this->post('/mfa', ['code' => '000000'])->assertStatus(429);
});

it('asks for a fresh code before sensitive actions after 10 minutes', function () {
    Route::middleware(['web', 'auth', 'mfa', 'mfa.fresh'])->get('/_test/sensitive', fn () => 'sensitive ok');

    $person = invitedPerson();
    $secret = signInFully($person, $this);

    $this->get('/_test/sensitive')->assertOk()->assertSee('sensitive ok');

    $this->travel(11)->minutes();
    $this->get('/_test/sensitive')->assertRedirect(route('mfa.challenge'));

    $this->post('/mfa', ['code' => nextCode($secret)])->assertSessionHasNoErrors()->assertRedirect('/_test/sensitive');
    $this->get('/_test/sensitive')->assertOk();
});

it('lets nobody read someone else\'s authenticator secret, not even the Owner', function () {
    $person = invitedPerson();
    signInFully($person, $this);
    $owner = Person::factory()->owner()->in(franceEntity())->create();

    $visible = app(AccessContext::class)->runAs($owner, fn () => MfaCredential::query()->count());
    $credential = app(AccessContext::class)->runAsSystem(fn () => MfaCredential::query()->firstOrFail());

    expect($visible)->toBe(0)
        ->and(Gate::forUser($owner)->allows('view', $credential))->toBeFalse();
});

it('never copies the authenticator secret or recovery codes into the audit log', function () {
    $person = invitedPerson();
    $secret = signInFully($person, $this);

    expect(json_encode(DB::table('audit_events')->get()))->not->toContain($secret);
});
