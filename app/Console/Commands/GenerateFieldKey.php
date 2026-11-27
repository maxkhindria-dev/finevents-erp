<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Prints a new FIELD_ENCRYPTION_KEY. It is shown once and never written to a
 * file: store it in Dashlane, then in Laravel Cloud's encrypted settings.
 */
class GenerateFieldKey extends Command
{
    protected $signature = 'finevents:generate-field-key';

    protected $description = 'Generate a new key for field-level encryption (shown once, never saved)';

    public function handle(): int
    {
        $this->line('base64:'.base64_encode(random_bytes(32)));
        $this->newLine();
        $this->warn('Store this in Dashlane and in Laravel Cloud. Changing it later makes existing encrypted fields unreadable until they are re-encrypted.');

        return self::SUCCESS;
    }
}
