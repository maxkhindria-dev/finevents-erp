<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Second factor for sign-in (CLAUDE.md §5.1). One authenticator-app secret per
| person, encrypted at field level, plus one-way hashes of their recovery codes.
| Passkeys / hardware keys for Owner and Super Admins arrive in step 4b.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfa_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained('people')->cascadeOnDelete();
            $table->string('type', 20)->default('totp');
            $table->text('secret');                          // encrypted (AES-256-GCM)
            $table->unsignedBigInteger('last_used_timestep')->nullable(); // blocks code replay
            $table->text('recovery_code_hashes');           // JSON list of bcrypt hashes
            $table->timestamp('confirmed_at')->nullable();
            $table->string('classification', 20)->default('restricted');
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_credentials');
    }
};
