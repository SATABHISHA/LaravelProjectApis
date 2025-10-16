<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'cf_order_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'cf_payment_id',
        'cashfree_response',
        'return_url',
        'description',
        'paid_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cashfree_response' => 'array',
        'paid_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'PAID';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }

    public function isPending(): bool
    {
        return $this->status === 'CREATED';
    }
}
