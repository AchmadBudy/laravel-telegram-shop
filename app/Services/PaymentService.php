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
use Telegram\Bot\FileUpload\InputFile;
use Illuminate\Support\Str;

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

                $telegramUser = TelegramUser::where('id', $deposit->telegram_user_id)->first();
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
                $productItems = ProductItem::where('transaction_id', $transaction->id)
                    ->lockForUpdate()
                    ->get();
                $productItems->each->update([
                    'is_sold' => false,
                    'transaction_id' => null
                ]);

                $telegramUser = TelegramUser::where('id', $transaction->telegram_user_id)->first();
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
            'telegram' => $telegramUser,
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
                    $productMessage .= "➜ {$detail->product->name} | {$detail->quantity}x\n";
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
                ➜ Payment Method : Balance
                ➜ Tanggal : {$transaction->updated_at}
                EOD;

                if ($isFile) {
                    $file = InputFile::createFromContents($productMessageFile, 'invoice.txt');
                }
            }

            DB::commit();
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

    public function createPaymentSingleProduct(string $idUser, string $idProduct, int $amount, string $paymentMethod, int $discount = 0): array
    {
        DB::beginTransaction();

        try {
            $product = Product::where('id', $idProduct)
                ->active()
                ->lockForUpdate()
                ->first();
            if (!$product) {
                throw new \Exception('Product not found');
            }

            $user = TelegramUser::where('telegram_id', $idUser)
                ->lockForUpdate()
                ->first();

            // check stock
            if ($product->stock < $amount) {
                throw new \Exception('Stock not enough');
            }

            // calculate total price
            $originalPrice = $product->price * $amount;
            $totalPrice = $originalPrice - $discount;

            // check if payment method is balance and user balance is enough
            if ($paymentMethod === 'balance' && $user->balance < $totalPrice) {
                throw new \Exception('Balance not enough');
            }


            // create transaction
            $transaction = Transaction::create([
                'telegram_user_id' => $user->id,
                // 'payment_number' => $paymentNumber,
                'total_price' => $totalPrice,
                'total_price_original' => $originalPrice,
                'discount' => $discount,
                'status' => OrderStatus::PENDING,
                // 'payment_number' => $paymentNumber,
            ]);

            // create payment number
            $paymentNumber = 'PAYMENT-' . Str::padLeft($transaction->id, 15, '0');


            // get product item
            $productItem = ProductItem::where('product_id', $product->id)
                ->where('is_sold', false)
                ->limit($amount)
                ->lockForUpdate()
                ->get();

            $transactionDetail = TransactionDetail::create([
                'transaction_id' => $transaction->id,
                'product_id' => $product->id,
                'quantity' => $amount,
                'price_each' => $product->price,
                'price_total' => $product->price * $amount,
            ]);

            // update product item
            $productItem->each->update([
                'is_sold' => true,
                'transaction_id' => $transaction->id,
            ]);

            // update product stock
            $product->stock -= $amount;
            $product->save();

            // check if payment method is balance or not
            if ($paymentMethod === 'balance') {
                // update user balance
                $user->update([
                    'balance' => $user->balance - $totalPrice
                ]);

                // update transaction with success status
                $transaction->update([
                    'status' => OrderStatus::SUCCESS,
                    'payment_status' => OrderStatus::SUCCESS,
                    'payment_number' => $paymentNumber,
                ]);

                // make message for user
                $productMessageFile = '';
                $isFile = false;
                $productMessage = <<<EOD
                ➜ {$product->name} | {$amount}x
                ➜ Harga Satuan : Rp {$product->price}
                EOD;
                $productMessage .= "```Item\n";
                if ($amount < 15) {
                    foreach ($productItem as $item) {
                        $productMessage .= "➜ {$item->item}\n";
                    }
                } else {
                    $productMessage .= "➜ Terlalu banyak item untuk ditampilkan, item akan dilampirkan melalui file\n";
                    $isFile = true;
                    $productMessageFile .= "Item {$product->name}\n";
                    foreach ($productItem as $item) {
                        $productMessageFile .= "➜ {$item->item}\n";
                    }
                }
                $productMessage .= "```";
                $message = <<<EOD
                *🔰 Payment Invoice*
                Items :
                {$productMessage}

                Detail :
                ➜ Order ID : {$transaction->payment_number}
                ➜ Total Harga : Rp {$transaction->total_price}
                ➜ Status : Berhasil
                ➜ Payment Method : {$transaction->payment_type}
                ➜ Tanggal : {$transaction->updated_at}
                EOD;

                if ($isFile) {
                    $file = InputFile::createFromContents($productMessageFile, 'invoice.txt');
                }
            } else {
                // call paydisini
                $response = $this->paydisiniService->createTransaction($paymentNumber, $totalPrice, $paymentMethod, 'Payment for ' . $amount . 'x ' . $product->name);
                if (!$response['success']) {
                    throw new \Exception($response['msg']);
                }

                // update transaction with payment link
                $transaction->update([
                    'payment_type' => $response['data']['service_name'],
                    'payment_link' => $response['data']['checkout_url'],
                    'payment_qr' => $response['data']['qrcode_url'],
                    'payment_status' => Str::lower($response['data']['status']),
                    'payment_number' => $paymentNumber,
                ]);

                // make message for user
                $message = <<<EOD
                *Payment Invoice*
                =================
                ➜ Order ID : {$transaction->payment_number}
                ➜ Product : {$product->name} | {$amount}x
                ➜ Harga Satuan : Rp {$product->price}
                ➜ Total Harga : Rp {$transaction->total_price}
                ➜ Status : Menunggu Pembayaran
                ➜ Payment Method : {$transaction->payment_type}

                Silahkan Melakukan Pembayaran Dengan Scan Qris Berikut 
                Harap segera lakukan pembayaran sebelum {$response['data']['expired']}
                EOD;
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }



        return [
            'success' => true,
            'data' => $transaction,
            'telegram' => $user,
            'isBalancePayment' => $paymentMethod === 'balance',
            'messageToUser' => $message,
            'isFile' => $isFile ?? false,
            'file_path' => $file ?? null
        ];
    }
}
