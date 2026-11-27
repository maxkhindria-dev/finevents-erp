<?php

use App\Access\AccessContext;
use App\Models\ProjectFinancial;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

afterEach(function () {
    // Each test changes the global encryption setting; put the default back.
    bootWithFieldKey(null);
});

function bootWithFieldKey(?string $key): void
{
    config(['finevents.field_encryption_key' => $key]);
    (new AppServiceProvider(app()))->boot();
}

it('stores budgets encrypted with the dedicated AES-256-GCM key, not the app key', function () {
    $rawKey = random_bytes(32);
    bootWithFieldKey('base64:'.base64_encode($rawKey));

    $financial = app(AccessContext::class)->runAsSystem(
        fn () => ProjectFinancial::factory()->create(['budget' => '125000'])
    );

    $stored = DB::table('project_financials')->where('id', $financial->id)->value('budget');

    expect($stored)->not->toContain('125000')
        ->and((new Encrypter($rawKey, 'aes-256-gcm'))->decrypt($stored, false))->toBe('125000');

    $appKey = base64_decode(substr((string) config('app.key'), 7));
    expect(fn () => (new Encrypter($appKey, 'aes-256-cbc'))->decrypt($stored, false))
        ->toThrow(DecryptException::class);

    // And reading it back through the model gives the plain value to permitted code.
    $reloaded = app(AccessContext::class)->runAsSystem(fn () => ProjectFinancial::query()->find($financial->id));
    expect($reloaded?->budget)->toBe('125000');
});

it('rejects a field key of the wrong length', function () {
    expect(fn () => bootWithFieldKey('base64:'.base64_encode('too-short')))
        ->toThrow(RuntimeException::class, '32-byte');
});

it('in production without a key, still boots but refuses to read or write encrypted fields', function () {
    $originalEnv = app()->environment();
    app()['env'] = 'production';
    config(['finevents.audit_chain_key' => 'base64:'.base64_encode(random_bytes(32))]); // isolate: only the field key is missing

    try {
        bootWithFieldKey(null); // must not throw: installs and builds need to boot

        expect(fn () => app(AccessContext::class)->runAsSystem(
            fn () => ProjectFinancial::factory()->create(['budget' => '1000'])
        ))->toThrow(RuntimeException::class, 'FIELD_ENCRYPTION_KEY is missing');
    } finally {
        app()['env'] = $originalEnv;
        bootWithFieldKey(null); // back to the test fallback
    }
});

it('generates a valid field key without saving it anywhere', function () {
    $this->artisan('finevents:generate-field-key')
        ->expectsOutputToContain('base64:')
        ->assertSuccessful();

    expect(file_exists(base_path('.env')) ? file_get_contents(base_path('.env')) : '')
        ->not->toMatch('/^FIELD_ENCRYPTION_KEY=base64:/m');
});
