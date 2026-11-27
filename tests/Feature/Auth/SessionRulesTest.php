<?php

/*
| Session rules (CLAUDE.md §5.1).
*/

use App\Access\AccessContext;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/AuthHelpers.php';

beforeEach(function () {
    config(['finevents.workspace_domain' => TEST_DOMAIN]);
    $this->withoutVite();
});

it('signs people out 12 hours after signing in, however active they are', function () {
    $person = invitedPerson();
    signInFully($person, $this);

    $this->travel(11)->hours();
    $this->get('/home')->assertOk();

    $this->travel(61)->minutes();
    $this->get('/home')->assertRedirect(route('login'));
    $this->assertGuest();
    expect(DB::table('audit_events')->where('action', 'auth.session_ended')->exists())->toBeTrue();
});

it('signs people out on their next click when they are suspended', function () {
    $person = invitedPerson();
    signInFully($person, $this);

    app(AccessContext::class)->runAsSystem(fn () => Person::query()->whereKey($person->id)->update(['status' => 'suspended']));
    nextRequestReloadsPerson();

    $this->get('/home')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('signs freelancers out when their access end date passes mid-session', function () {
    $person = invitedPerson(['kind' => 'freelancer', 'access_expires_at' => now()->addHour()]);
    signInFully($person, $this);

    $this->travel(2)->hours();

    $this->get('/home')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('signs out only by a form post, and logs it', function () {
    $person = invitedPerson();
    signInFully($person, $this);

    $this->get('/sign-out')->assertStatus(405); // a link or image can't sign someone out
    $this->post('/sign-out')->assertRedirect(route('login'));

    $this->assertGuest();
    expect(DB::table('audit_events')->where('action', 'auth.signed_out')->exists())->toBeTrue();
});

it('sends signed-in visitors of the sign-in page to the app', function () {
    $person = invitedPerson();
    signInFully($person, $this);

    $this->get('/')->assertRedirect(route('home'));
});
