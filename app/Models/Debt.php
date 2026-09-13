<?php

namespace App\Models;

use App\Enums\DebtDirection;
use App\Enums\TransactionType;
use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property DebtDirection $direction
 * @property string $counterparty_name
 * @property int $original_amount
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['workspace_id', 'direction', 'counterparty_name', 'original_amount', 'notes'])]
class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function remainder(): int
    {
        $repaid = (int) $this->transactions()
            ->where('type', TransactionType::DebtRepayment)
            ->sum('amount');

        $left = $this->original_amount - $repaid;

        return $left < 0 ? 0 : $left;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => DebtDirection::class,
            'original_amount' => 'integer',
        ];
    }
}
