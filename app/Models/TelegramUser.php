<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'balance',
    ];

    protected $casts = [
        'balance' => 'integer',
    ];

    /**
     * Get the transactions for the user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
