<?php

namespace App\Auth;

use App\Access\AccessContext;
use App\Models\MfaCredential;
use App\Models\Person;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Authenticator-app codes (TOTP, RFC 6238) and one-time recovery codes.
 */
class TwoFactor
{
    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AccessContext $context,
    ) {}

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /** QR code (inline SVG) for the authenticator app to scan. */
    public function qrCodeSvg(Person $person, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl((string) config('finevents.mfa_issuer'), $person->email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    public function credentialFor(Person $person): ?MfaCredential
    {
        return $this->context->runAs($person, fn () => MfaCredential::query()
            ->where('person_id', $person->id)->whereNotNull('confirmed_at')->first());
    }

    /**
     * Saves a confirmed authenticator app and returns the recovery codes,
     * which are shown once and stored only as one-way hashes.
     *
     * @return list<string>|null null when the code doesn't match
     */
    public function enrol(Person $person, string $secret, string $code): ?array
    {
        // Passing 0 (never null) makes the library return the code's time slot,
        // which is stored so the same code can never be accepted twice.
        $timestep = $this->google2fa->verifyKeyNewer($secret, $this->normalise($code), 0);

        if (! is_int($timestep)) {
            return null;
        }

        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = Str::lower(Str::random(5).'-'.Str::random(5));
        }

        $this->context->runAs($person, function () use ($person, $secret, $timestep, $codes): void {
            $credential = new MfaCredential;
            $credential->forceFill([
                'person_id' => $person->id,
                'secret' => $secret,
                'last_used_timestep' => $timestep,
                'recovery_code_hashes' => array_map(fn (string $c) => Hash::make($c), $codes),
                'confirmed_at' => now(),
            ])->save();
        });

        return $codes;
    }

    /**
     * Checks an authenticator code (each code works once) or, failing that,
     * a recovery code (each works once, then is removed).
     *
     * @return 'totp'|'recovery'|null
     */
    public function verify(Person $person, string $input): ?string
    {
        $credential = $this->credentialFor($person);

        if ($credential === null) {
            return null;
        }

        $code = $this->normalise($input);

        if (preg_match('/^\d{6}$/', $code) === 1) {
            // Only codes newer than the last one accepted count (no replay).
            $timestep = $this->google2fa->verifyKeyNewer($credential->secret, $code, $credential->last_used_timestep ?? 0);

            if (! is_int($timestep)) {
                return null;
            }

            $this->context->runAs($person, fn () => $credential->forceFill(['last_used_timestep' => $timestep])->save());

            return 'totp';
        }

        foreach ($credential->recovery_code_hashes as $index => $hash) {
            if (Hash::check(Str::lower($code), $hash)) {
                $remaining = $credential->recovery_code_hashes;
                unset($remaining[$index]);

                $this->context->runAs($person, fn () => $credential->forceFill([
                    'recovery_code_hashes' => array_values($remaining),
                ])->save());

                return 'recovery';
            }
        }

        return null;
    }

    private function normalise(string $input): string
    {
        $trimmed = trim($input);

        // "123 456" → "123456"; recovery codes keep their dash.
        return preg_match('/^[\d\s]+$/', $trimmed) === 1 ? (string) preg_replace('/\s+/', '', $trimmed) : $trimmed;
    }
}
