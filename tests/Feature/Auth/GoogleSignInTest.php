<?php

/*
| Google sign-in (CLAUDE.md §5.1): FinEvents Workspace accounts only, invited
| and active people only, and always the same Google account per person.
*/

use App\Access\AccessContext;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

require_once __DIR__.'/AuthHelpers.php';

beforeEach(function () {
    config(['finevents.workspace_domain' => TEST_DOMAIN]);
});

function lastAudit(string $action): ?object
{
    return DB::table('audit_events')->where('action', $action)->orderByDesc('seq')->first();
}

it('shows the sign-in page to visitors, and nothing of the app', function () {
    $this->withoutVite()->get('/')
        ->assertOk()
        ->assertSee('Continue with Google')
        ->assertDontSee('Arrives in Phase 2');
});

it('sends people to Google asking only for name and email, limited to the FinEvents domain', function () {
    config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret']);

    $location = (string) $this->get('/auth/google')->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $scopes = explode(' ', (string) $query['scope']);
    sort($scopes);

    expect($location)->toStartWith('https://accounts.google.com/')
        ->and($query['hd'])->toBe(TEST_DOMAIN)
        ->and($query['prompt'])->toBe('select_account')
        ->and($scopes)->toBe(['email', 'openid', 'profile']); // nothing more: no Gmail, Drive or Calendar
});

it('signs in an invited, active FinEvents person and asks for the second factor', function () {
    $person = invitedPerson();
    googleReturns();

    $this->get('/auth/google/callback')->assertRedirect(route('home'));
    $this->get('/home')->assertRedirect(route('mfa.setup'));

    $this->assertAuthenticatedAs($person);
    expect(app(AccessContext::class)->runAsSystem(fn () => $person->fresh()?->google_id))->toBe('google-123')
        ->and(lastAudit('auth.signed_in'))->not->toBeNull();
});

it('refuses Google accounts that should not get in', function (array $google, array $personAttributes, string $reason) {
    invitedPerson($personAttributes);
    googleReturns(...$google);

    $this->get('/auth/google/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
    expect(json_decode((string) lastAudit('auth.sign_in_refused')?->after, true)['reason'])->toBe($reason);
})->with([
    'personal Gmail account' => [['alex@gmail.com', 'google-123', ['hd' => null]], [], 'not a FinEvents Workspace account'],
    'another company\'s Workspace' => [['alex@othercorp.test', 'google-123', ['hd' => 'othercorp.test']], [], 'not a FinEvents Workspace account'],
    'right Workspace, address on another domain' => [['alex@othercorp.test', 'google-123'], [], 'email not verified on the FinEvents domain'],
    'email not verified by Google' => [['alex@'.TEST_DOMAIN, 'google-123', ['email_verified' => false]], [], 'email not verified on the FinEvents domain'],
    'FinEvents account never invited' => [['someone.else@'.TEST_DOMAIN, 'google-999'], [], 'not invited'],
    'suspended person' => [[], ['status' => 'suspended'], 'account suspended'],
    'offboarded person' => [[], ['status' => 'offboarded'], 'account offboarded'],
    'freelancer past their end date' => [[], ['kind' => 'freelancer', 'access_expires_at' => now()->subDay()], 'account expired'],
    'recreated Google account with the same email' => [['alex@'.TEST_DOMAIN, 'google-NEW'], ['google_id' => 'google-OLD'], 'different Google account for this email'],
]);

it('refuses everyone if the FinEvents domain is not configured', function () {
    config(['finevents.workspace_domain' => null]);
    invitedPerson();
    googleReturns();

    $this->get('/auth/google/callback')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('handles a cancelled or failed Google round trip without signing anyone in', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andThrow(new InvalidStateException);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHas('error');
    $this->assertGuest();
});

it('gives a new session ID at sign-in (prevents session fixation)', function () {
    invitedPerson();
    googleReturns();

    $this->get('/');
    $before = session()->getId();
    $this->get('/auth/google/callback');

    expect(session()->getId())->not->toBe($before);
});

it('throttles repeated sign-in attempts', function () {
    googleReturns('nobody@'.TEST_DOMAIN, 'google-x');

    foreach (range(1, 10) as $i) {
        $this->get('/auth/google/callback');
    }

    $this->get('/auth/google/callback')->assertStatus(429);
});

it('never shows the refusal reason on screen', function () {
    invitedPerson(['status' => 'suspended']);
    googleReturns();

    $this->get('/auth/google/callback');

    expect((string) session('error'))->not->toContain('suspended');
});

it('keeps the Google account ID out of the audit log', function () {
    invitedPerson();
    googleReturns();
    $this->get('/auth/google/callback');

    expect(json_encode(DB::table('audit_events')->get()))->not->toContain('google-123');
});

it('does not let a person be loaded by session for someone else after offboarding', function () {
    $person = invitedPerson();
    googleReturns();
    $this->get('/auth/google/callback');

    app(AccessContext::class)->runAsSystem(fn () => Person::query()->whereKey($person->id)->update(['status' => 'offboarded']));
    nextRequestReloadsPerson();

    $this->get('/home')->assertRedirect(route('login'));
    $this->assertGuest();
});
