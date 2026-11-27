<?php

namespace App\Http\Middleware;

use App\Audit\AuditLogger;
use App\Models\Person;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checked on every request of a signed-in person:
 *  - signed out 12 hours after signing in, however active they are;
 *  - signed out immediately if their account was suspended, offboarded or
 *    has expired since they signed in.
 */
class EnforceSessionRules
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $person = $request->user();

        if (! $person instanceof Person) {
            return $next($request);
        }

        $signedInAt = (int) $request->session()->get('signed_in_at', 0);
        $maxSeconds = (int) config('finevents.session_max_hours') * 3600;

        $reason = match (true) {
            ! $person->isActive() => 'account no longer active',
            $signedInAt === 0 || now()->getTimestamp() - $signedInAt > $maxSeconds => 'session time limit reached',
            default => null,
        };

        if ($reason === null) {
            return $next($request);
        }

        $this->audit->record('auth.session_ended', 'person', $person->id, $person->primary_entity_id, after: ['reason' => $reason]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out. Please sign in again.');
    }
}
