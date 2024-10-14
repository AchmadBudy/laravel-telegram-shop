<?php

namespace App\Services;

use App\Settings\PaydisiniSettings;
use Illuminate\Support\Facades\Http;

class PaydisiniService
{
    private $key;
    private $feeType;
    public $url = 'https://api.paydisini.co.id/v1/';

    public function __construct()
    {
        $paydisiniSettings = new PaydisiniSettings();
        $this->key = $paydisiniSettings->api_key;
        $this->feeType = $paydisiniSettings->fee_type;
    }

    public function showChannelPembayaran(): array
    {
        $body = [
            'key' => $this->key,
            'request' => 'payment_channel',
            'signature' => md5($this->key . 'PaymentChannel')
        ];


        try {
            $response = Http::asForm()->post($this->url, $body);
            // Handle the response if needed
            return $response->json();
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    public function createTransaction(string $uniqueCode, int $totalPrice, string $serviceCode, string $note): bool|array
    {
        $timeExpired = 3600; // in seconds (3600 for best practice)

        $body = [
            'key' => $this->key,
            'request' => 'new',
            'unique_code' => $uniqueCode,
            'service' => $serviceCode,
            'amount' => $totalPrice,
            'note' => $note,
            'valid_time' => $timeExpired,
            'type_fee' => $this->feeType,
            'payment_guide' => FALSE, // Pilih TRUE jika ingin menampilkan panduan pembayaran
            'signature' => md5($this->key .  $uniqueCode . $serviceCode . $totalPrice . $timeExpired . 'NewTransaction'),
            // 'return_url' => 'https://yourwebsite.com/'
        ];

        try {
            $response = Http::asForm()->post($this->url, $body);

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new \Exception($response->json());
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function checkTransaction(string $uniqueCode): bool|array
    {
        $body = [
            'key' => $this->key,
            'request' => 'status',
            'unique_code' => $uniqueCode,
            'signature' => md5($this->key . $uniqueCode . 'StatusTransaction')
        ];

        $response = Http::asForm()->post($this->url, $body);

        return $response->json();
    }

    public function cancelTransaction(string $uniqueCode): bool|array
    {
        $body = [
            'key' => $this->key,
            'request' => 'cancel',
            'unique_code' => $uniqueCode,
            'signature' => md5($this->key . $uniqueCode . 'CancelTransaction')
        ];

        $response = Http::asForm()->post($this->url, $body);

        return $response->json();
    }
}
