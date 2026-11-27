<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Clients, projects, their members, project financials and document metadata.
| File contents stay in Google Drive; only IDs and metadata are stored here.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('sector')->nullable();
            $table->foreignId('owner_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('classification', 20)->default('restricted');
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->index(['entity_id', 'name']);
        });

        // People who may see a client they are not otherwise in scope for.
        Schema::create('client_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['client_id', 'person_id']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // e.g. FR-2026-014, used on invoices and in Drive
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('venue')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->string('drive_folder_id')->nullable();
            $table->string('classification', 20)->default('restricted');
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('role_on_project')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'person_id']);
        });

        // Sensitive: kept apart from projects so it can be locked down separately.
        Schema::create('project_financials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('budget')->nullable(); // encrypted at field level (AES-256-GCM)
            $table->string('classification', 20)->default('restricted');
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('drive_file_id');
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 40);  // execution_plan, agenda, minutes, ...
            $table->string('title');
            $table->string('visibility', 30)->default('internal');
            $table->string('classification', 20)->default('confidential');
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->index('drive_file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('project_financials');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('client_members');
        Schema::dropIfExists('clients');
    }
};
