<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TillSession extends Model
{
    use HasFactory;

    protected $table = 'till_sessions';

    protected $fillable = [
        'branch_id',
        'cashier_id',
        'opening_amount',
        'opened_at',
        'closing_amount',
        'expected_amount',
        'variance',
        'status',
        'closed_at',
        'closed_by',
        'opening_note',
        'closing_note',
        'offline_session_id',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'opening_amount'  => 'decimal:2',
            'closing_amount'  => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'variance'        => 'decimal:2',
            'opened_at'       => 'datetime',
            'closed_at'       => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'till_session_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(TillCashMovement::class, 'till_session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Calculate financial summary and expected cash for this till session.
     * Formula:
     * Expected Cash = Opening Amount + Cash Sales − Cash Refunds + Cash Added − Cash Removed
     */
    public function calculateSummary(): array
    {
        $openingAmount = (float) $this->opening_amount;

        // Cash sales: sum of cash collected from sales tagged to this till session
        $salesQuery = $this->sales();
        $cashSales = (float) (clone $salesQuery)
            ->where(function ($q) {
                $q->where('payment_method', 'Cash')
                  ->orWhere('cash', '>', 0);
            })
            ->sum('cash');

        // Total sales revenue across all payment methods
        $totalSales = (float) (clone $salesQuery)->sum('final_total');
        $salesCount = (int) (clone $salesQuery)->count();

        // Bank transfer / other non-cash payments in this session
        $bankTransferSales = (float) (clone $salesQuery)->sum('bank_transfer');

        // Cash refunds for sales in this till session or performed by cashier during this session
        $refundQuery = SaleReturnItem::where(function ($q) {
            $q->whereHas('sale', fn($s) => $s->where('till_session_id', $this->id))
              ->orWhere(function ($sub) {
                  $sub->where('user_id', $this->cashier_id)
                      ->where('created_at', '>=', $this->opened_at)
                      ->when($this->closed_at, fn($c) => $c->where('created_at', '<=', $this->closed_at));
              });
        });
        $cashRefunds = (float) $refundQuery->sum('refund_amount');

        // Cash Movements (Add / Remove)
        $cashAdded = (float) $this->cashMovements()->where('type', 'add')->sum('amount');
        $cashRemoved = (float) $this->cashMovements()->where('type', 'remove')->sum('amount');

        $expectedCash = round($openingAmount + $cashSales - $cashRefunds + $cashAdded - $cashRemoved, 2);

        $actualCounted = $this->closing_amount !== null ? (float) $this->closing_amount : null;
        $variance = $actualCounted !== null ? round($actualCounted - $expectedCash, 2) : null;

        return [
            'opening_amount'       => $openingAmount,
            'cash_sales'           => round($cashSales, 2),
            'bank_transfer_sales'  => round($bankTransferSales, 2),
            'total_sales'          => round($totalSales, 2),
            'sales_count'          => $salesCount,
            'cash_refunds'         => round($cashRefunds, 2),
            'cash_added'           => round($cashAdded, 2),
            'cash_removed'         => round($cashRemoved, 2),
            'expected_cash'        => $expectedCash,
            'actual_counted'       => $actualCounted,
            'variance'             => $variance,
        ];
    }
}
