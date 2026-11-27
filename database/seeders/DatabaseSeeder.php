<?php

namespace Database\Seeders;

use App\Access\AccessContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeding is trusted system work, so it runs without record filters.
     * Generated demo data is never created in production.
     */
    public function run(): void
    {
        app(AccessContext::class)->runAsSystem(function (): void {
            $this->call(EntitySeeder::class);

            if (! app()->isProduction()) {
                $this->call(DemoDataSeeder::class);
            }
        });
    }
}
