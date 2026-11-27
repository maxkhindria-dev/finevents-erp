<?php

namespace App\Audit;

use Illuminate\Support\Facades\DB;

/**
 * Re-checks the whole audit chain. Returns null when intact, otherwise a plain
 * description of the first problem found.
 */
class AuditChainVerifier
{
    public function __construct(private readonly AuditLogger $logger) {}

    public function firstProblem(): ?string
    {
        $key = $this->logger->key();
        $expectedSeq = 1;
        $expectedPrev = AuditLogger::GENESIS_HASH;

        foreach (DB::table('audit_events')->lazyById(500, 'seq') as $row) {
            $row = (array) $row;
            $seq = (int) $row['seq'];

            if ($seq !== $expectedSeq) {
                return "Entry {$expectedSeq} is missing (next entry found is {$seq}).";
            }

            if ($row['prev_hash'] !== $expectedPrev) {
                return "Entry {$seq} does not link to the entry before it.";
            }

            if (! hash_equals(AuditLogger::seal($row, $key), (string) $row['hash'])) {
                return "Entry {$seq} has been altered: its seal does not match its contents.";
            }

            $expectedPrev = (string) $row['hash'];
            $expectedSeq++;
        }

        $head = DB::table('audit_chain')->where('id', 1)->first();

        if ($head === null) {
            return 'The audit chain head is missing.';
        }

        if ((int) $head->last_seq !== $expectedSeq - 1 || $head->last_hash !== $expectedPrev) {
            return 'The latest entries are missing: the chain head points past the last entry found.';
        }

        return null;
    }
}
