<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per Moonetix transaction_id: the rewards ledger.
 *
 * @property string $transaction_id
 * @property string $wall_user_id
 * @property int $reward_micro
 * @property int $status 1 credited, 2 reversed
 * @property string|null $offer_name
 */
class MoonetixTransaction extends Model
{
    public const CREDITED = 1;

    public const REVERSED = 2;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'reward_micro' => 'integer',
            'status' => 'integer',
        ];
    }
}
