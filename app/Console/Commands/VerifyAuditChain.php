<?php

namespace App\Console\Commands;

use App\Audit\AuditChainVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerifyAuditChain extends Command
{
    protected $signature = 'finevents:audit:verify';

    protected $description = 'Check that no audit log entry has been altered, removed or reordered';

    public function handle(AuditChainVerifier $verifier): int
    {
        $problem = $verifier->firstProblem();
        $count = DB::table('audit_events')->count();

        if ($problem === null) {
            $this->info("Audit log intact: {$count} entries checked.");

            return self::SUCCESS;
        }

        // Owner email alerts for this arrive with the alerting work in step 6.
        Log::critical('Audit log integrity check FAILED', ['problem' => $problem]);
        $this->error("Audit log integrity check FAILED: {$problem}");

        return self::FAILURE;
    }
}
