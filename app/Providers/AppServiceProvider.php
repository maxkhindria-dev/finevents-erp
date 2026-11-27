<?php

namespace App\Providers;

use App\Access\AccessContext;
use App\Access\UnscopedPersonProvider;
use App\Models\Person;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
            throw new RuntimeException('FIELD_ENCRYPTION_KEY is missing. Refusing to start in production.');
        }

        // Local development and tests fall back to the app key.
    }
}
