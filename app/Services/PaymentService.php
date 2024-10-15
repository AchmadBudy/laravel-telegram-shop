<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DepositHistory;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use PgSql\Lob;

class PaymentService
{
    protected $paydisiniService;
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->paydisiniService = new PaydisiniService();
    }

    public function cancelPayment(
        string $transactionCode,
        bool $cancelByAdmin = false,
        bool $cancelByUser = false,
        bool $cancelByTime = false
    ): array {
        $status = '';
        if ($cancelByAdmin) {
            $response = $this->paydisiniService->cancelTransaction($transactionCode);
            if (!$response['success']) {
                return [
                    'success' => false,
                    'message' => $response['msg']
                ];
            }

            $status = OrderStatus::CANCELBYADMIN;
        } elseif ($cancelByUser) {
            $response = $this->paydisiniService->cancelTransaction($transactionCode);
            if (!$response['success']) {
                return [
                    'success' => false,
                    'message' => $response['msg']
                ];
            }
            $status = OrderStatus::CANCELBYUSER;
        } elseif ($cancelByTime) {
            $status = OrderStatus::CANCELBYTIMEOUT;
        }

        $codeTransaction = explode('-', $transactionCode)[0];

        // start transaction
        DB::beginTransaction();

        try {
            if ($codeTransaction == 'DEPOSIT') {
                $deposit = DepositHistory::where('payment_number', $transactionCode)
                    ->lockForUpdate()
                    ->first();
                $deposit->update([
                    'status' => $status,
                    'payment_status' => 'canceled',
                ]);
            } elseif ($codeTransaction == 'PAYMENT') {
                // update order status
                $transaction = Transaction::where('payment_number', $transactionCode)
                    ->lockForUpdate()
                    ->first();
                $transaction->update([
                    'status' => $status,
                    'payment_status' => 'canceled',
                ]);

                // mark productitem to not sold
                $transaction->productItem
                    ->lockForUpdate()
                    ->update([
                        'is_sold' => false,
                        'transaction_id' => null,
                    ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }


        return [
            'success' => true,
        ];
    }
}
