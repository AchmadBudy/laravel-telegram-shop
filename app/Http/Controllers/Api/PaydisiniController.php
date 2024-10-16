<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\TelegramService;
use App\Settings\PaydisiniSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaydisiniController extends Controller
{
    public function webhook(Request $request)
    {
        $setting = new PaydisiniSettings();
        $paymentService = new PaymentService();
        $telegramService = new TelegramService();
        // check if key is correct and ip is from paydisini
        if ($request->key !== $setting->api_key || $request->ip() !== '194.233.92.170') {
            return response()
                ->json([
                    'success' => "false",
                    'error' => 'Invalid key or ip'
                ]);
        }

        $uniqueCode = $request->unique_code;
        $status = $request->status;
        $sign = md5($setting->api_key . $uniqueCode . 'CallbackStatus');

        if ($sign !== $request->signature) {
            return response()
                ->json([
                    'success' => "false",
                    'error' => 'Invalid signature'
                ]);
        }

        // update transaction status
        if ($status === 'Success') {
            // success transaction
            $response = $paymentService->successPayment($uniqueCode);
            if (!$response['success']) {
                Log::error('Failed to update payment: ' . $uniqueCode);
                Log::error($response['message']);
            } else {
                // delete old message
                if ($response['data']->message_id) {
                    $telegramService->deleteMessage($response['telegram']->telegram_id, $response['data']->message_id);
                }
                // send new message but check if isFile is true or false
                if ($response['isFile']) {
                    try {
                        $telegramService->telegram->sendDocument([
                            'chat_id' => $response['telegram']->telegram_id,
                            'document' => $response['file_path'],
                            'caption' => $response['messageToUser'],
                            'parse_mode' => 'Markdown',
                        ]);
                    } catch (\Throwable $th) {
                        Log::error('Failed to send file: ' . $uniqueCode);
                        Log::error($th->getMessage());
                    }
                } else {
                    $telegramService->sendMessage($response['telegram']->telegram_id, $response['messageToUser']);
                }
            }
            return response()->json([
                'success' => "true",
                'message' => 'Success'
            ]);
        } else {
            // failed transaction
            $response = $paymentService->cancelPayment($uniqueCode, cancelByTime: true);
            if (!$response['success']) {
                Log::error('Failed to cancel payment: ' . $uniqueCode);
                Log::error($response['message']);
            } else {
                // delete old message if exist
                if ($response['data']->message_id) {
                    $telegramService->deleteMessage($response['telegram']->telegram_id, $response['data']->message_id);
                }
                // send new message
                $telegramService->sendMessage($response['telegram']->telegram_id, 'Payment failed, jika masih berminat silahkan coba lagi');
            }
            return response()->json([
                'success' => "true",
                'message' => 'Canceled'
            ]);
        }
    }
}
