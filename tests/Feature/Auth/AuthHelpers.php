<?php

use App\Access\AccessContext;
use App\Models\Entity;
use App\Models\Person;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

const TEST_DOMAIN = 'finevents-example.test';

function franceEntity(): Entity
{
    return app(AccessContext::class)->runAsSystem(
        fn () => Entity::query()->where('code', 'FR')->first() ?? Entity::factory()->code('FR')->create()
    );
}

function invitedPerson(array $attributes = []): Person
{
    return Person::factory()->in(franceEntity())->create(['email' => 'alex@'.TEST_DOMAIN] + $attributes);
}

/**
 * Pretends Google returned this account after the person chose it.
 *
 * @param  array<string, mixed>  $raw
 */
function googleReturns(string $email = 'alex@'.TEST_DOMAIN, string $id = 'google-123', array $raw = []): void
{
    $user = (new GoogleUser)
        ->setRaw($raw + ['hd' => TEST_DOMAIN, 'email_verified' => true]) // the test's values win
        ->map(['id' => $id, 'email' => $email, 'name' => 'Alex Example']);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($user);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

function currentCode(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

/**
 * The code an authenticator app will show 30 seconds from now. Laravel's
 * test "time travel" doesn't move the authenticator library's clock, so tests
 * that need a second, different code use this.
 */
function nextCode(string $secret): string
{
    $google2fa = app(Google2FA::class);

    return $google2fa->oathTotp($secret, $google2fa->getTimestamp() + 1);
}

/** Signs a person all the way in (Google + authenticator app) and returns their secret. */
function signInFully(Person $person, TestCase $test): string
{
    googleReturns($person->email, $person->google_id ?? 'google-'.$person->id);
    $test->get('/auth/google/callback');
    $test->get('/mfa/setup');
    $secret = (string) session('mfa_pending_secret');
    $test->post('/mfa/setup', ['code' => currentCode($secret)]);

    return $secret;
}

/**
 * In tests the app is reused between requests, so the signed-in person stays
 * cached. A real request always reloads them; this makes the next test
 * request do the same.
 */
function nextRequestReloadsPerson(): void
{
    app('auth')->forgetGuards();
}
