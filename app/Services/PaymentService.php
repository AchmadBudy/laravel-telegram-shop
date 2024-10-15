<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\OrderStatus;
use App\Models\DepositHistory;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PgSql\Lob;
use Telegram\Bot\FileUpload\InputFile;

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
            $status = OrderStatus::CANCELBYADMIN;
        } elseif ($cancelByUser) {
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
                // check if deposit is already canceled
                if ($deposit->status === OrderStatus::SUCCESS || $deposit->status === OrderStatus::CANCELBYADMIN || $deposit->status === OrderStatus::CANCELBYUSER || $deposit->status === OrderStatus::CANCELBYTIMEOUT) {
                    throw new \Exception('Deposit already canceled');
                }

                $deposit->update([
                    'status' => $status,
                    'payment_status' => $status,
                ]);
            } elseif ($codeTransaction == 'PAYMENT') {
                // update order status
                $transaction = Transaction::where('payment_number', $transactionCode)
                    ->lockForUpdate()
                    ->first();

                // check if transaction is already canceled
                if ($transaction->status === OrderStatus::SUCCESS || $transaction->status === OrderStatus::CANCELBYADMIN || $transaction->status === OrderStatus::CANCELBYUSER || $transaction->status === OrderStatus::CANCELBYTIMEOUT) {
                    throw new \Exception('Transaction already canceled');
                }

                $transaction->update([
                    'status' => $status,
                    'payment_status' => $status,
                ]);

                // mark productitem to not sold
                $transaction->productItem
                    ->lockForUpdate()
                    ->update([
                        'is_sold' => false,
                        'transaction_id' => null,
                    ]);
            }

            // send cancel request to paydisini if cancelbyuser or cancelbyadmin
            if ($cancelByUser || $cancelByAdmin) {
                $response = $this->paydisiniService->cancelTransaction($transactionCode);
                if (!$response['success']) {
                    throw new \Exception($response['msg']);
                }
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
            'data' => $deposit ?? $transaction,
            'telegram' => TelegramUser::where('user_id', $deposit->user_id)->first()
        ];
    }

    public function successPayment(string $transactionCode, bool $isFromSaldo = false): array
    {
        DB::beginTransaction();
        try {
            $isFile = false;
            $codeTransaction = explode('-', $transactionCode)[0];
            // check if transaction is deposit or payment
            if ($codeTransaction == 'DEPOSIT') {
                $deposit = DepositHistory::where('payment_number', $transactionCode)
                    ->lockForUpdate()
                    ->first();
                $telegramUser = TelegramUser::where('id', $deposit->telegram_user_id)
                    ->lockForUpdate()
                    ->first();

                // check if deposit is not pending
                if ($deposit->status !== DepositStatus::PENDING->value) {
                    throw new \Exception('Deposit already processed');
                }

                $deposit->update([
                    'status' => DepositStatus::SUCCESS,
                    'payment_status' => DepositStatus::SUCCESS,
                ]);

                // add balance to user
                $telegramUser->update([
                    'balance' => $telegramUser->balance + $deposit->total_deposit
                ]);

                DB::commit();

                // make message for user
                $message = "Deposit Rp." . number_format($deposit->total_deposit, 0, ',', '.') . " berhasil, saldo anda sekarang Rp " . number_format($telegramUser->balance, 0, ',', '.');
            } elseif ($codeTransaction == 'PAYMENT') {
                $transaction = Transaction::where('payment_number', $transactionCode)
                    ->lockForUpdate()
                    ->first();
                $telegramUser = TelegramUser::where('id', $transaction->telegram_user_id)
                    ->lockForUpdate()
                    ->first();

                // check if transaction is not pending
                if ($transaction->status !== OrderStatus::PENDING->value) {
                    throw new \Exception('Transaction already processed');
                }

                $transaction->update([
                    'status' => OrderStatus::SUCCESS,
                    'payment_status' => OrderStatus::SUCCESS,
                ]);

                // commit transaction
                DB::commit();

                // get all product item
                $details = TransactionDetail::where('transaction_id', $transaction->id)
                    ->with(['product', 'product.items' => function ($query) use ($transaction) {
                        $query->where('transaction_id', $transaction->id);
                    }])
                    ->get();

                // make message for user
                $productMessageFile = '';
                $productMessage = '';
                foreach ($details as $detail) {
                    $productMessage .= "➜ {$detail->product->name} \\| {$detail->quantity}x\n";
                    $productMessage .= "➜ Harga Satuan : Rp" . number_format($detail->price_each) . "\n";
                    $productMessage .= "```Item\n";
                    if ($detail->quantity < 15) {
                        foreach ($detail->product->items as $item) {
                            $productMessage .= "➜ {$item->item}\n";
                        }
                    } else {
                        $productMessage .= "➜ Terlalu banyak item untuk ditampilkan, item akan dilampirkan melalui file\n";
                        $isFile = true;
                        $productMessageFile .= "Item {$detail->product->name}\n";
                        foreach ($detail->product->items as $item) {
                            $productMessageFile .= "➜ {$item->item}\n";
                        }
                    }
                    $productMessage .= "```";
                }
                $message = <<<EOD
                *🔰 Payment Invoice*
                Items :
                {$productMessage}

                Detail :
                ➜ Order ID : {$transaction->payment_number}
                ➜ Total Harga : Rp {$transaction->total_price}
                ➜ Status : Berhasil
                ➜ Payment Method : {$transaction->payment_method}
                ➜ Tanggal : {$transaction->updated_at}
                EOD;

                if ($isFile) {
                    $file = InputFile::createFromContents($productMessageFile, 'invoice.txt');
                }
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }


        return [
            'success' => true,
            'data' => $deposit ?? $transaction,
            'telegram' => $telegramUser,
            'messageToUser' => $message,
            'isFile' => $isFile,
            'file_path' => $file ?? null
        ];
    }
}
