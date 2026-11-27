<?php

namespace App\Http\Controllers\Auth;

use App\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SignOutController extends Controller
{
    public function __invoke(Request $request, AuditLogger $audit): RedirectResponse
    {
        $person = $request->user();

        if ($person instanceof Person) {
            $audit->record('auth.signed_out', 'person', $person->id, $person->primary_entity_id);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have signed out.');
    }
}
