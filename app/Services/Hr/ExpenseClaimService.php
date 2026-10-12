<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseClaimService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
        protected ApprovalService $approvals
    ) {
    }

    public function createDraft(Employee $employee, array $data, User $actor): ExpenseClaim
    {
        $category = ExpenseCategory::where('company_id', $employee->company_id)
            ->whereKey($data['expense_category_id'])
            ->firstOrFail();

        $amount = number_format((float) ($data['amount'] ?? 0), 2, '.', '');
        if (\bccomp($amount, '0.00', 2) !== 1) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }
        if ($category->max_amount !== null && \bccomp($amount, (string) $category->max_amount, 2) === 1) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds category policy limit.']);
        }

        $hash = hash('sha256', implode('|', [
            $employee->id,
            $category->id,
            $amount,
            $data['currency'] ?? 'INR',
            $data['expense_date'],
            strtolower(trim($data['title'])),
        ]));

        if (ExpenseClaim::where('duplicate_hash', $hash)->whereNotIn('status', ['rejected', 'cancelled'])->exists()) {
            throw ValidationException::withMessages(['title' => 'Possible duplicate expense claim detected.']);
        }

        $claim = ExpenseClaim::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'expense_category_id' => $category->id,
            'amount' => $amount,
            'currency' => $data['currency'] ?? 'INR',
            'expense_date' => $data['expense_date'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'receipt_path' => $data['receipt_path'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'duplicate_hash' => $hash,
        ]);

        $this->activityLogger->log('expense_draft_created', 'hr', $claim);

        return $claim;
    }

    public function submit(ExpenseClaim $claim, User $actor): ExpenseClaim
    {
        return DB::transaction(function () use ($claim) {
            if ($claim->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only drafts can be submitted.']);
            }
            $claim->update(['status' => 'submitted']);
            $approval = $this->approvals->submit($claim, $claim->employee, 'expense');
            $claim->update(['approval_request_id' => $approval->id]);

            return $claim->fresh();
        });
    }

    public function approve(ExpenseClaim $claim, User $actor, ?string $comment = null): ExpenseClaim
    {
        return DB::transaction(function () use ($claim, $actor, $comment) {
            $approval = $claim->approvalRequest
                ?? ($claim->approval_request_id ? \App\Models\ApprovalRequest::find($claim->approval_request_id) : null);
            if ($approval) {
                $this->approvals->approve($approval, $actor, $comment);
            }
            $claim->update(['status' => 'approved']);

            return $claim->fresh();
        });
    }

    public function markScheduled(ExpenseClaim $claim, User $actor): ExpenseClaim
    {
        if ($claim->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'Only approved claims can be scheduled.']);
        }
        if (in_array($claim->payment_status, ['paid', 'via_payroll'], true)) {
            throw ValidationException::withMessages(['payment_status' => 'Claim already paid or linked to payroll.']);
        }
        $claim->update(['payment_status' => 'scheduled', 'status' => 'scheduled']);

        return $claim->fresh();
    }

    public function markPaid(ExpenseClaim $claim, User $actor, string $reference): ExpenseClaim
    {
        return DB::transaction(function () use ($claim, $reference) {
            $claim = ExpenseClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if ($claim->payment_status === 'via_payroll') {
                throw ValidationException::withMessages(['payment_status' => 'Already reimbursed via payroll; duplicate payment blocked.']);
            }
            if ($claim->payment_status === 'paid') {
                throw ValidationException::withMessages(['payment_status' => 'Already paid.']);
            }
            if (! in_array($claim->status, ['approved', 'scheduled'], true)) {
                throw ValidationException::withMessages(['status' => 'Claim is not payable.']);
            }
            $claim->update([
                'status' => 'paid',
                'payment_status' => 'paid',
                'payment_reference' => $reference,
            ]);

            return $claim->fresh();
        });
    }
}
