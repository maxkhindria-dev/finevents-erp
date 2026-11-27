<?php

use App\Access\AccessContext;
use App\Models\Entity;
use App\Models\Person;

beforeEach(fn () => $this->withoutVite());

it('shows visitors the sign-in page', function () {
    $this->get('/')->assertOk()->assertSee('Sign in to FinEvents ERP');
});

it('shows the FinEvents navigation shell once signed in with the second factor', function () {
    $person = Person::factory()->in(app(AccessContext::class)->runAsSystem(fn () => Entity::factory()->code('FR')->create()))->create();

    $this->actingAs($person)
        ->withSession(['signed_in_at' => now()->timestamp, 'mfa_verified_at' => now()->timestamp])
        ->get('/home')
        ->assertOk()
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
