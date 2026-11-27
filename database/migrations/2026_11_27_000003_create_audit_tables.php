<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| The audit log (CLAUDE.md §5.5): append-only and tamper-evident.
|
|  - audit_events: one row per event, each sealed to the previous one with an
|    HMAC (key held outside the database). Before/after values are stored as
|    exact text, not MySQL JSON, so the seal can be re-checked byte for byte.
|  - audit_chain: a single row holding the latest seal, locked while writing so
|    two simultaneous events can't fork the chain.
|  - audit_archives: a record of each nightly copy to write-once storage.
|
| Database triggers refuse UPDATE and DELETE on audit_events for every database
| login, migrations included. The app's own login also has no UPDATE, DELETE or
| DROP permission on it (see `php artisan finevents:db:privileges`).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seq')->unique();          // 1, 2, 3 … with no gaps
            $table->string('occurred_at', 26);                    // UTC, Y-m-d H:i:s.u (exact text)
            $table->string('actor_type', 10);                     // person | system
            $table->unsignedBigInteger('actor_person_id')->nullable()->index();
            $table->string('action', 80)->index();                // e.g. project.updated
            $table->string('target_type', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->longText('before')->nullable();               // canonical JSON text
            $table->longText('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('country', 2)->nullable();
            $table->char('prev_hash', 64);
            $table->char('hash', 64)->unique();
            $table->index(['target_type', 'target_id']);
            $table->index('occurred_at');
        });

        Schema::create('audit_chain', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();         // always 1
            $table->unsignedBigInteger('last_seq');
            $table->char('last_hash', 64);
        });

        DB::table('audit_chain')->insert(['id' => 1, 'last_seq' => 0, 'last_hash' => str_repeat('0', 64)]);

        Schema::create('audit_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_seq');
            $table->unsignedBigInteger('to_seq')->unique();
            $table->char('last_hash', 64);
            $table->string('disk', 40);
            $table->string('path');
            $table->char('file_sha256', 64);
            $table->timestamp('created_at')->useCurrent();
        });

        $this->createAppendOnlyTriggers();
    }

    public function down(): void
    {
        $this->dropAppendOnlyTriggers();
        Schema::dropIfExists('audit_archives');
        Schema::dropIfExists('audit_chain');
        Schema::dropIfExists('audit_events');
    }

    private function createAppendOnlyTriggers(): void
    {
        $message = 'audit_events is append-only';

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit_events BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
            DB::unprepared("CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit_events BEGIN SELECT RAISE(ABORT, '{$message}'); END;");

            return;
        }

        DB::unprepared("CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'");
        DB::unprepared("CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'");
    }

    private function dropAppendOnlyTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_delete');
    }
};
