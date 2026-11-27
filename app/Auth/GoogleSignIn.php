<?php

namespace App\Auth;

use App\Access\AccessContext;
use App\Audit\AuditLogger;
use App\Models\Person;
use Laravel\Socialite\Contracts\User as GoogleUser;

/**
 * Decides whether a Google account may sign in, and as whom.
 *
 * Every check must pass:
 *  1. The account belongs to the FinEvents Google Workspace ("hd" claim, set by
 *     Google itself), and the email is on that domain and verified.
 *  2. A matching person was invited in the ERP and is active (not suspended,
 *     offboarded or past their freelancer expiry date).
 *  3. The Google account ID matches the one first used by that person. If a
 *     Google account is deleted and recreated with the same email, the new one
 *     is refused until an admin resets the link.
 */
class GoogleSignIn
{
    public function __construct(
        private readonly AccessContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function resolve(GoogleUser $google): Person
    {
        $domain = strtolower((string) config('finevents.workspace_domain'));
        $raw = method_exists($google, 'getRaw') ? (array) $google->getRaw() : [];
        $email = strtolower((string) $google->getEmail());
        $googleId = (string) $google->getId();

        try {
            if ($domain === '') {
                throw new SignInRefused('workspace domain not configured');
            }

            if (strtolower((string) ($raw['hd'] ?? '')) !== $domain) {
                throw new SignInRefused('not a FinEvents Workspace account');
            }

            if (! str_ends_with($email, '@'.$domain) || ($raw['email_verified'] ?? false) !== true) {
                throw new SignInRefused('email not verified on the FinEvents domain');
            }

            if ($googleId === '') {
                throw new SignInRefused('no Google account ID');
            }

            $person = $this->context->runAsSystem(
                fn () => Person::query()->where('google_id', $googleId)->first()
                    ?? Person::query()->where('email', $email)->first()
            );

            if ($person === null) {
                throw new SignInRefused('not invited');
            }

            if ($person->google_id !== null && $person->google_id !== $googleId) {
                throw new SignInRefused('different Google account for this email');
            }

            if (! $person->isActive()) {
                throw new SignInRefused('account '.($person->access_expires_at?->isPast() ? 'expired' : $person->status->value));
            }
        } catch (SignInRefused $refused) {
            $this->audit->record('auth.sign_in_refused', after: ['email' => $email, 'reason' => $refused->reason]);

            throw $refused;
        }

        $this->context->runAs($person, function () use ($person, $googleId, $email): void {
            $person->forceFill([
                'google_id' => $googleId,
                'email' => $email,
                'last_login_at' => now(),
            ])->save();
        });

        return $person;
    }
}
