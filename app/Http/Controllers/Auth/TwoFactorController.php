<?php

namespace App\Http\Controllers\Auth;

use App\Audit\AuditLogger;
use App\Auth\TwoFactor;
use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactor $twoFactor,
        private readonly AuditLogger $audit,
    ) {}

    /** First sign-in: scan a QR code with an authenticator app. */
    public function showSetup(Request $request): View|RedirectResponse
    {
        $person = $this->person($request);

        if ($this->twoFactor->credentialFor($person) !== null) {
            return redirect()->route('mfa.challenge');
        }

        $secret = (string) ($request->session()->get('mfa_pending_secret') ?? $this->twoFactor->newSecret());
        $request->session()->put('mfa_pending_secret', $secret);

        return view('auth.mfa-setup', [
            'qrCode' => $this->twoFactor->qrCodeSvg($person, $secret),
            'secret' => $secret,
        ]);
    }

    public function confirmSetup(Request $request): View|RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $person = $this->person($request);
        $secret = $request->session()->get('mfa_pending_secret');

        if (! is_string($secret) || $this->twoFactor->credentialFor($person) !== null) {
            return redirect()->route('mfa.setup');
        }

        $recoveryCodes = $this->twoFactor->enrol($person, $secret, (string) $request->input('code'));

        if ($recoveryCodes === null) {
            $this->audit->record('auth.mfa_failed', 'person', $person->id, $person->primary_entity_id, after: ['during' => 'setup']);

            return back()->withErrors(['code' => 'That code didn\'t match. Check the time on your phone and try the newest code.']);
        }

        $request->session()->forget('mfa_pending_secret');
        $this->markVerified($request);
        $this->audit->record('auth.mfa_enrolled', 'person', $person->id, $person->primary_entity_id);

        // Shown once, never stored in readable form.
        return view('auth.mfa-recovery-codes', ['codes' => $recoveryCodes]);
    }

    public function showChallenge(Request $request): View|RedirectResponse
    {
        if ($this->twoFactor->credentialFor($this->person($request)) === null) {
            return redirect()->route('mfa.setup');
        }

        return view('auth.mfa-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $person = $this->person($request);

        $method = $this->twoFactor->verify($person, (string) $request->input('code'));

        if ($method === null) {
            $this->audit->record('auth.mfa_failed', 'person', $person->id, $person->primary_entity_id);

            return back()->withErrors(['code' => 'That code didn\'t work. Try the newest code from your app, or a recovery code.']);
        }

        $this->markVerified($request);
        $this->audit->record($method === 'recovery' ? 'auth.recovery_code_used' : 'auth.mfa_passed', 'person', $person->id, $person->primary_entity_id);

        return redirect()->intended(route('home'));
    }

    private function markVerified(Request $request): void
    {
        $request->session()->regenerate();
        $request->session()->put('mfa_verified_at', now()->getTimestamp());
    }

    private function person(Request $request): Person
    {
        $person = $request->user();
        abort_unless($person instanceof Person, 403);

        return $person;
    }
}
