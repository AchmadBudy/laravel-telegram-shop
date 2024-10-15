<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_user_id',
        'total_deposit',
        'status',
        'message_id',
        'payment_type',
        'payment_status',
        'payment_link',
        'payment_qr',
        'payment_number',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'telegram_user_id');
    }
}
