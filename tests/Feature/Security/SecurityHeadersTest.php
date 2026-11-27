<?php

beforeEach(fn () => $this->withoutVite());

it('sends the baseline security headers on every page', function (string $url) {
    $response = $this->get($url);

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeaderMissing('X-Powered-By');

    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
})->with(['/', '/up', '/a-page-that-does-not-exist']);

it('sends a strict content security policy with no outside domains', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("base-uri 'none'")
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+'/")
        ->not->toContain('unsafe-inline')
        ->not->toContain('unsafe-eval')
        ->not->toContain('https://')
        ->not->toContain('*');
});

it('uses a fresh CSP nonce for every request', function () {
    $nonce = fn () => preg_match("/'nonce-([^']+)'/", $this->get('/')->headers->get('Content-Security-Policy'), $m) ? $m[1] : null;

    expect($nonce())->not->toBeNull()->not->toBe($nonce());
});

it('adds HSTS on HTTPS requests only', function () {
    $this->get('https://localhost/')
        ->assertHeader('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');

    $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
});

it('ships secure session defaults when no environment override is set', function () {
    $config = require base_path('config/session.php');

    // These defaults protect production even if an environment variable is forgotten.
    expect(file_get_contents(base_path('config/session.php')))
        ->toContain("env('SESSION_LIFETIME', 720)")
        ->toContain("env('SESSION_ENCRYPT', true)")
        ->toContain("env('SESSION_SECURE_COOKIE', true)");

    expect($config['http_only'])->toBeTrue()
        ->and($config['same_site'])->toBe('lax');
});
