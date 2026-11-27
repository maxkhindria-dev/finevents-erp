<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Entities, teams, people and the access tables that scope them
| (docs/phase-1-plan.md §A).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();          // FR, UK, US
            $table->string('name');
            $table->string('country', 2);                  // ISO 3166-1 alpha-2
            $table->string('currency', 3);                 // ISO 4217
            $table->string('accounting_system', 20);       // pennylane | xero
            $table->string('classification', 20)->default('internal');
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('lead_person_id')->nullable(); // FK added below
            $table->string('classification', 20)->default('internal');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['entity_id', 'name']);
        });

        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('google_id')->nullable()->unique(); // filled at first sign-in
            $table->string('email')->unique();
            $table->string('full_name');
            $table->string('role', 20)->default('user')->index();
            // Only one Owner account may exist (the break-glass account is handled separately).
            $table->boolean('owner_slot')->nullable()->unique()
                ->storedAs("case when role = 'owner' then 1 else null end");
            $table->foreignId('primary_entity_id')->constrained('entities')->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('employee');
            $table->timestamp('access_expires_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('last_login_at')->nullable();
            $table->string('classification', 20)->default('confidential');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreign('lead_person_id')->references('id')->on('people')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('people')->nullOnDelete();
        });

        Schema::table('people', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('people')->nullOnDelete();
        });

        // Extra entities a person may see, beyond their primary entity.
        Schema::create('person_entity_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->unique(['person_id', 'entity_id']);
        });

        // Explicit permission to see a sensitive field type in one entity.
        Schema::create('sensitive_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('field', 30);
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->string('classification', 20)->default('confidential');
            $table->timestamps();
            $table->unique(['person_id', 'field', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_grants');
        Schema::dropIfExists('person_entity_access');
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['lead_person_id']);
            $table->dropForeign(['created_by']);
        });
        Schema::dropIfExists('people');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('entities');
    }
};
