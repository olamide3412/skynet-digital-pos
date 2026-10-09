<?php

namespace App\Modules\Accounting\Services;

use App\Models\Branch;
use App\Models\PosSettings;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\FinancialPeriod;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostingService
{
    /**
     * Create a balanced double-entry journal entry.
     * Supports both array parameter syntax and positional argument syntax.
     */
    public static function post(
        string|array $eventOrParams,
        ?Model $source = null,
        array $lines = [],
        ?string $description = null,
        ?Carbon $entryDate = null,
        ?int $branchId = null,
        ?int $userId = null
    ): ?JournalEntry {
        $instance = new self();

        if (is_array($eventOrParams)) {
            $params = $eventOrParams;
            $event = $params['event'] ?? 'manual';
            $source = $params['source'] ?? null;
            $lines = $params['lines'] ?? [];
            $description = $params['description'] ?? null;
            $entryDate = isset($params['entry_date']) ? Carbon::parse($params['entry_date']) : null;
            $branchId = $params['branch_id'] ?? null;
            $userId = $params['created_by'] ?? ($params['user_id'] ?? null);
            $sourceType = $params['source_type'] ?? null;
            $sourceId = $params['source_id'] ?? null;

            return $instance->executePost($event, $source, $lines, $description, $entryDate, $branchId, $userId, $sourceType, $sourceId);
        }

        return $instance->executePost($eventOrParams, $source, $lines, $description, $entryDate, $branchId, $userId);
    }

    /**
     * Reverse an existing journal entry.
     */
    public static function reverseEntry(JournalEntry $originalEntry, ?string $reason = null, ?User $user = null): JournalEntry
    {
        return (new self())->executeReverseEntry($originalEntry, $reason, $user);
    }

    /**
     * Execute posting transaction.
     */
    public function executePost(
        string $event,
        ?Model $source,
        array $lines,
        ?string $description = null,
        ?Carbon $entryDate = null,
        ?int $branchId = null,
        ?int $userId = null,
        ?string $customSourceType = null,
        ?int $customSourceId = null
    ): ?JournalEntry {
        $entryDate = $entryDate ? $entryDate->copy() : now();
        $sourceType = $customSourceType ?: ($source ? get_class($source) : 'manual');
        $sourceId = $customSourceId ?: ($source?->id ?? null);

        // Resolve branch and organization
        $branch = null;
        if ($branchId) {
            $branch = Branch::find($branchId);
        } elseif ($source && isset($source->branch_id)) {
            $branch = Branch::find($source->branch_id);
            $branchId = $branch?->id;
        }

        $orgId = $branch?->organization_id;

        // Check if branch accounting is started (Decision 6)
        if ($branchId) {
            $settings = PosSettings::forBranch($branchId);
            if ($settings->accounting_started_at && $entryDate->lt($settings->accounting_started_at->startOfDay())) {
                Log::info("Posting skipped: transaction date {$entryDate} is before accounting_started_at {$settings->accounting_started_at} for branch {$branchId}");
                return null;
            }
        }

        // Idempotency check (§2): One source record + event -> at most one active journal entry
        if ($sourceType && $sourceId) {
            $existing = JournalEntry::where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('event', $event)
                ->where('status', 'posted')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // Validate double-entry balance: Σ Debit === Σ Credit
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $sanitizedLines = [];

        foreach ($lines as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            // Skip zero-value lines
            if ($debit == 0.0 && $credit == 0.0) {
                continue;
            }

            if ($debit < 0 || $credit < 0) {
                throw new \InvalidArgumentException('Journal entry amounts cannot be negative.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $sanitizedLines[] = [
                'account_id'  => $line['account_id'],
                'debit'       => $debit,
                'credit'      => $credit,
                'memo'        => $line['memo'] ?? null,
                'customer_id' => $line['customer_id'] ?? null,
                'vendor_id'   => $line['vendor_id'] ?? null,
            ];
        }

        if (empty($sanitizedLines)) {
            Log::info("Posting skipped: no non-zero lines for event {$event}");
            return null;
        }

        // Must balance to 2 decimal places
        if (abs(round($totalDebit - $totalCredit, 2)) >= 0.01) {
            $diff = abs($totalDebit - $totalCredit);
            throw new \InvalidArgumentException(
                "Journal entry is out of balance by {$diff}. Total debit: {$totalDebit}, Total credit: {$totalCredit}."
            );
        }

        // Validate all accounts exist and belong to the correct branch/org
        $accountIds = array_column($sanitizedLines, 'account_id');
        $validAccounts = Account::whereIn('id', $accountIds)
            ->where('is_active', true)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->pluck('id')
            ->all();

        if (count($validAccounts) !== count(array_unique($accountIds))) {
            throw new \InvalidArgumentException('One or more accounts in the journal entry are invalid, inactive, or belong to another branch.');
        }

        return DB::transaction(function () use (
            $orgId, $branchId, $sourceType, $sourceId, $event, $description,
            $entryDate, $sanitizedLines, $userId
        ) {
            $postingDate = $entryDate;
            $period = null;
            $periodMemo = null;

            // Check financial period status (§7, Decision 5)
            if ($branchId) {
                $closedPeriod = FinancialPeriod::where('branch_id', $branchId)
                    ->where('starts_on', '<=', $entryDate->toDateString())
                    ->where('ends_on', '>=', $entryDate->toDateString())
                    ->where('status', 'closed')
                    ->first();

                if ($closedPeriod) {
                    // Falls into closed period -> post into current open period with memo (Decision 5)
                    $openPeriod = FinancialPeriod::where('branch_id', $branchId)
                        ->where('status', 'open')
                        ->orderBy('starts_on', 'desc')
                        ->first();

                    if ($openPeriod) {
                        $period = $openPeriod;
                        $postingDate = now();
                        $periodMemo = "Late entry for closed period ({$closedPeriod->name})";
                    }
                } else {
                    $period = FinancialPeriod::where('branch_id', $branchId)
                        ->where('starts_on', '<=', $entryDate->toDateString())
                        ->where('ends_on', '>=', $entryDate->toDateString())
                        ->first();
                }
            }

            // Generate entry number
            $entryNumber = $this->generateEntryNumber($branchId);

            $fullDescription = $description;
            if ($periodMemo) {
                $fullDescription = $fullDescription ? "{$fullDescription} [{$periodMemo}]" : $periodMemo;
            }

            $journalEntry = JournalEntry::create([
                'organization_id'     => $orgId ?? 0,
                'branch_id'           => $branchId,
                'entry_number'        => $entryNumber,
                'entry_date'          => $postingDate->toDateString(),
                'description'         => $fullDescription,
                'source_type'         => $sourceType,
                'source_id'           => $sourceId,
                'event'               => $event,
                'status'              => 'posted',
                'financial_period_id' => $period?->id,
                'created_by'          => $userId ?? auth()->id(),
            ]);

            foreach ($sanitizedLines as $line) {
                $memo = $line['memo'];
                if ($periodMemo && !$memo) {
                    $memo = $periodMemo;
                }

                JournalLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id'       => $line['account_id'],
                    'debit'            => $line['debit'],
                    'credit'           => $line['credit'],
                    'memo'             => $memo,
                    'customer_id'      => $line['customer_id'],
                    'vendor_id'        => $line['vendor_id'],
                ]);
            }

            return $journalEntry->load('lines.account');
        });
    }

    /**
     * Execute reversing entry.
     */
    public function executeReverseEntry(JournalEntry $originalEntry, ?string $reason = null, ?User $user = null): JournalEntry
    {
        if ($originalEntry->status !== 'posted') {
            throw new \InvalidArgumentException("Cannot reverse a journal entry with status '{$originalEntry->status}'.");
        }

        if ($originalEntry->is_reversal) {
            throw new \InvalidArgumentException("Cannot reverse an entry that is already a reversal.");
        }

        return DB::transaction(function () use ($originalEntry, $reason, $user) {
            $originalEntry->load('lines');

            $lines = [];
            foreach ($originalEntry->lines as $line) {
                $lines[] = [
                    'account_id'  => $line->account_id,
                    'debit'       => $line->credit,
                    'credit'      => $line->debit,
                    'memo'        => "Reversal: " . ($line->memo ?: $originalEntry->entry_number),
                    'customer_id' => $line->customer_id,
                    'vendor_id'   => $line->vendor_id,
                ];
            }

            $entryNumber = $this->generateEntryNumber($originalEntry->branch_id);
            $reversalDesc = "Reversal of {$originalEntry->entry_number}" . ($reason ? " ({$reason})" : "");

            $reversal = JournalEntry::create([
                'organization_id'     => $originalEntry->organization_id,
                'branch_id'           => $originalEntry->branch_id,
                'entry_number'        => $entryNumber,
                'entry_date'          => now()->toDateString(),
                'description'         => $reversalDesc,
                'source_type'         => $originalEntry->source_type,
                'source_id'           => $originalEntry->source_id,
                'event'               => 'reversal',
                'status'              => 'posted',
                'reversal_of_id'      => $originalEntry->id,
                'financial_period_id' => $originalEntry->financial_period_id,
                'created_by'          => $user?->id ?? auth()->id(),
            ]);

            foreach ($lines as $line) {
                JournalLine::create(array_merge($line, [
                    'journal_entry_id' => $reversal->id,
                ]));
            }

            $originalEntry->update([
                'status' => 'reversed',
            ]);

            return $reversal;
        });
    }

    /**
     * Generate sequential entry number per branch (e.g. JE-202610-0001).
     */
    protected function generateEntryNumber(?int $branchId): string
    {
        $prefix = 'JE-' . now()->format('Ym') . '-';
        $last = JournalEntry::where('branch_id', $branchId)
            ->where('entry_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->value('entry_number');

        if ($last) {
            $seq = (int) substr($last, strlen($prefix)) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
