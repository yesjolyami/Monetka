<?php

namespace App\Models;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceType;
use Database\Factories\RecurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property RecurrenceType $type
 * @property int $account_id
 * @property int|null $counterparty_account_id
 * @property int|null $category_id
 * @property int $amount
 * @property string|null $description
 * @property RecurrenceFrequency $frequency
 * @property Carbon $next_occurred_on
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'workspace_id',
    'type',
    'account_id',
    'counterparty_account_id',
    'category_id',
    'amount',
    'description',
    'frequency',
    'next_occurred_on',
    'is_active',
])]
class Recurrence extends Model
{
    /** @use HasFactory<RecurrenceFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function counterpartyAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counterparty_account_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RecurrenceType::class,
            'amount' => 'integer',
            'frequency' => RecurrenceFrequency::class,
            'next_occurred_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
