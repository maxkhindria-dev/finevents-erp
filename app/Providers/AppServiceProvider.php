<?php

namespace App\Providers;

use App\Access\AccessContext;
use App\Access\UnscopedPersonProvider;
use App\Models\Person;
use App\Security\MissingFieldKeyEncrypter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One access context per request (or per job).
        $this->app->scoped(AccessContext::class);
    }

    public function boot(): void
    {
        // Outside production, mistakes such as reading data that wasn't loaded
        // or assigning a field that isn't allowed fail loudly instead of silently.
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->configureFieldEncryption();

        Auth::provider('unscoped-people', fn (Application $app, array $config) => new UnscopedPersonProvider(
            $app->make('hash'),
            $config['model'],
        ));

        // Anyone active may open the app itself; what they see inside is up to the policies.
        Gate::define('use-app', fn (Person $person): bool => true);

        // Throttle sign-in attempts per IP, and second-factor attempts per person.
        RateLimiter::for('sign-in', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
        RateLimiter::for('mfa', fn (Request $request) => Limit::perMinute(5)->by('mfa:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Layer 1 guard rail: suspended, offboarded or expired people are refused
        // everything, before any individual permission rule is consulted.
        Gate::before(function (mixed $user): ?bool {
            if (! $user instanceof Person || ! $user->isActive()) {
                return false;
            }

            return null; // fall through to the policy
        });
    }

    /**
     * Sensitive fields (budgets now; bank details, rates, salaries and OAuth
     * tokens later) are encrypted with AES-256-GCM using a dedicated key,
     * separate from the general app key.
     */
    private function configureFieldEncryption(): void
    {
        $key = config('finevents.field_encryption_key');

        if (is_string($key) && $key !== '') {
            $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

            if (! is_string($raw) || strlen($raw) !== 32) {
                throw new RuntimeException('FIELD_ENCRYPTION_KEY must be a 32-byte key (base64:...).');
            }

            Model::encryptUsing(new Encrypter($raw, 'aes-256-gcm'));

            return;
        }

        if ($this->app->isProduction()) {
            // Boot (so installs and builds work) but refuse to touch encrypted fields.
            Model::encryptUsing(new MissingFieldKeyEncrypter);

            return;
        }

        // Local development and tests fall back to the app key.
        Model::encryptUsing(null);
    }
}
