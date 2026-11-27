<?php

namespace App\Http\Middleware;

use App\Auth\TwoFactor;
use App\Models\Person;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * No page opens until this session has passed the second factor.
 * People without an authenticator app yet are sent to set one up.
 */
class EnsureMfaVerified
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $person = $request->user();

        if ($person instanceof Person && $request->session()->get('mfa_verified_at') === null) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route($this->twoFactor->credentialFor($person) === null ? 'mfa.setup' : 'mfa.challenge');
        }

        return $next($request);
    }
}
