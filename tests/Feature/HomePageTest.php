<?php

beforeEach(fn () => $this->withoutVite());

it('shows the home page with the FinEvents navigation shell', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('FinEvents ERP')
        ->assertSee('CRM')
        ->assertSee('Time off')
        ->assertSee('Brand tools')
        ->assertSee('Arrives in Phase 2');
});

it('tells search engines not to index the app', function () {
    $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('answers the health check used by uptime monitoring', function () {
    $this->get('/up')->assertOk();
});
