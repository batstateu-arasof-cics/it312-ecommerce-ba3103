<?php

namespace App\Modules\Orders\Models;

use App\Models\User;
use App\Modules\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Core model. Owner: Orders team. */
class Order extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const SHIPPED = 'shipped';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::PENDING, self::PAID, self::SHIPPED, self::COMPLETED, self::CANCELLED];

    public const PAYMENT_METHODS = ['cod', 'online'];

    /** Allowed status changes: from => [to, ...]. See the Flowcharts tab of the System Design doc. */
    public const TRANSITIONS = [
        self::PENDING => [self::PAID, self::CANCELLED],
        self::PAID => [self::SHIPPED, self::CANCELLED],
        self::SHIPPED => [self::COMPLETED],
        self::COMPLETED => [],
        self::CANCELLED => [],
    ];

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** @return list<string> */
    public function nextStatuses(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function isCancellable(): bool
    {
        return $this->status === self::PENDING;
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cod' => 'Cash on Delivery',
            'online' => 'Online (GCash / Card)',
            default => strtoupper((string) $this->payment_method),
        };
    }

    protected $fillable = [
        'user_id', 'status', 'subtotal', 'discount', 'shipping_fee', 'total',
        'voucher_code', 'payment_method', 'address_id', 'shipping_address',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** Order number shown to customers, e.g. ORD-000012 */
    public function number(): string
    {
        return 'ORD-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
