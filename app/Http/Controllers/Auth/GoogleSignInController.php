<?php

namespace App\Http\Controllers\Auth;

use App\Audit\AuditLogger;
use App\Auth\GoogleSignIn;
use App\Auth\SignInRefused;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleSignInController extends Controller
{
    /** Sends the browser to Google, asking only for name and email. */
    public function redirect(): SymfonyRedirect
    {
        $google = Socialite::driver('google');

        if (! $google instanceof GoogleProvider) {
            throw new LogicException('Google sign-in is not configured with the Google driver.');
        }

        return $google
            ->scopes(['openid', 'email', 'profile'])
            ->with(['hd' => (string) config('finevents.workspace_domain'), 'prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request, GoogleSignIn $signIn, AuditLogger $audit): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user(); // also checks the anti-forgery "state"
        } catch (Throwable) {
            return redirect()->route('login')->with('error', 'Google sign-in did not complete. Please try again.');
        }

        try {
            $person = $signIn->resolve($google);
        } catch (SignInRefused) {
            return redirect()->route('login')
                ->with('error', 'This Google account can\'t sign in to FinEvents ERP. If you think it should, ask your admin.');
        }

        Auth::guard('web')->login($person);
        $request->session()->regenerate();   // new session ID at sign-in
        $request->session()->put('signed_in_at', now()->getTimestamp());
        $request->session()->forget('mfa_verified_at');

        $audit->record('auth.signed_in', 'person', $person->id, $person->primary_entity_id, after: ['step' => 'google, awaiting second factor']);

        return redirect()->intended(route('home'));
    }
}
