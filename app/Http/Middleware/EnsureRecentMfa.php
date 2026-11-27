<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For sensitive actions (exports, role changes, finance syncs): asks for a
 * fresh authenticator code if the last one was entered too long ago.
 * Usage: ->middleware('mfa.fresh') or ->middleware('mfa.fresh:5') for 5 minutes.
 */
class EnsureRecentMfa
{
    public function handle(Request $request, Closure $next, ?string $minutes = null): Response
    {
        $limit = (int) ($minutes ?? config('finevents.mfa_fresh_minutes'));
        $verifiedAt = (int) $request->session()->get('mfa_verified_at', 0);

        if (now()->getTimestamp() - $verifiedAt > $limit * 60) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('mfa.challenge')
                ->with('status', 'For your security, please confirm it\'s you before continuing.');
        }

        return $next($request);
    }
}
