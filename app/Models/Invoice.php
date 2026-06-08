<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'company_plan_subscription_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'status',
        'type',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'description',
        'notes',
        'currency',
        'line_items',
        'payment_terms',
        'external_invoice_id',
        'sent_at',
        'viewed_at',
        'paid_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'paid_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'line_items' => 'array',
        'payment_terms' => 'array',
    ];

    // Relaciones
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CompanyPlanSubscription::class, 'company_plan_subscription_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    // Métodos útiles
    public function getBalance(): float
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue' || ($this->due_date->isPast() && !in_array($this->status, ['paid', 'cancelled', 'refunded']));
    }

    public function isPaid(): bool
    {
        return $this->paid_amount >= $this->total_amount;
    }

    public function markAsSent()
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsViewed()
    {
        $this->update([
            'viewed_at' => now(),
        ]);
    }

    public function calculateReminder(): ?string
    {
        $daysUntilDue = now()->diffInDays($this->due_date, false);

        if ($daysUntilDue <= 0) {
            return 'overdue';
        } elseif ($daysUntilDue <= 3) {
            return 'due_soon';
        }

        return null;
    }
}
