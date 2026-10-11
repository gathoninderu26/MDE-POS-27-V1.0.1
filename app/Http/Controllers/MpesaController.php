<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MpesaController – Safaricom Daraja API Integration
 *
 * Supports both Sandbox (test) and Production environments.
 * Supports per-tenant Daraja credentials & Till / Paybill numbers,
 * with graceful fallback to system-level .env configuration.
 */
class MpesaController extends Controller
{
    protected string $baseUrl;

    protected string $consumerKey;

    protected string $consumerSecret;

    protected string $shortcode;

    protected string $passkey;

    protected string $callbackUrl;

    protected string $accountReference;

    protected string $transactionType;

    public function __construct()
    {
        $env = config('mpesa.env', 'sandbox');

        $this->baseUrl = $env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        $this->consumerKey = config('mpesa.consumer_key', '');
        $this->consumerSecret = config('mpesa.consumer_secret', '');
        $this->shortcode = config('mpesa.shortcode', '174379');
        $this->passkey = config('mpesa.passkey', '');
        $this->callbackUrl = config('mpesa.callback_url', url('/mpesa/callback'));
        $this->accountReference = config('mpesa.account_reference', 'MDE POS');
        $this->transactionType = config('mpesa.transaction_type', 'CustomerBuyGoodsOnline');
    }

    /**
     * Resolves tenant-specific Daraja credentials and Till/Paybill numbers,
     * falling back to default application .env configuration.
     */
    protected function resolveDarajaConfig(?Tenant $tenant = null): array
    {
        $settings = $tenant?->settings ?? [];
        $pmList = $settings['payment_methods'] ?? [];

        $consumerKey = $settings['mpesa_consumer_key'] ?? $settings['daraja_consumer_key'] ?? $this->consumerKey;
        $consumerSecret = $settings['mpesa_consumer_secret'] ?? $settings['daraja_consumer_secret'] ?? $this->consumerSecret;
        $passkey = $settings['mpesa_passkey'] ?? $settings['daraja_passkey'] ?? $this->passkey;

        $shortcode = null;
        $txType = null;

        foreach ($pmList as $pm) {
            if (($pm['type'] ?? '') === 'Mobile Money' && ($pm['enabled'] ?? true)) {
                if (! empty($pm['details']['till_number'])) {
                    $shortcode = (string) $pm['details']['till_number'];
                    $txType = 'CustomerBuyGoodsOnline';
                    break;
                } elseif (! empty($pm['details']['business_number'])) {
                    $shortcode = (string) $pm['details']['business_number'];
                    $txType = 'CustomerPayBillOnline';
                    break;
                }
            }
        }

        if (! $shortcode) {
            $shortcode = (string) ($settings['mpesa_till_number'] ?? $settings['mpesa_shortcode'] ?? $this->shortcode);
            $txType = $settings['mpesa_transaction_type'] ?? $this->transactionType;
        }

        $env = $settings['mpesa_env'] ?? config('mpesa.env', 'sandbox');
        $baseUrl = $env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        return [
            'baseUrl' => $baseUrl,
            'consumerKey' => $consumerKey,
            'consumerSecret' => $consumerSecret,
            'shortcode' => $shortcode,
            'passkey' => $passkey,
            'callbackUrl' => $this->callbackUrl,
            'accountReference' => $tenant?->name ?? $this->accountReference,
            'transactionType' => $txType ?? $this->transactionType,
        ];
    }

    // ─── OAuth Token ───────────────────────────────────────────────────────────

    protected function getAccessToken(?array $cfg = null): ?string
    {
        $key = $cfg['consumerKey'] ?? $this->consumerKey;
        $secret = $cfg['consumerSecret'] ?? $this->consumerSecret;
        $url = $cfg['baseUrl'] ?? $this->baseUrl;

        if (empty($key) || empty($secret)) {
            return null;
        }

        $cacheKey = 'mpesa_access_token_'.md5($key.$secret);

        return Cache::remember($cacheKey, now()->addMinutes(55), function () use ($key, $secret, $url) {
            try {
                $response = Http::withBasicAuth($key, $secret)
                    ->timeout(15)
                    ->get("{$url}/oauth/v1/generate", ['grant_type' => 'client_credentials']);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error('M-Pesa OAuth failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::error('M-Pesa OAuth exception', ['message' => $e->getMessage()]);
            }

            return null;
        });
    }

    // ─── STK Push ──────────────────────────────────────────────────────────────

    /**
     * POST /mpesa/stk-push
     * Body (JSON): { phone, amount, ref? }
     */
    public function stkPush(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:9|max:15',
            'amount' => 'required|numeric|min:1',
            'ref' => 'nullable|string|max:20',
        ]);

        $phone = $this->normalizePhone($request->input('phone'));
        $amount = (int) ceil($request->input('amount'));
        $ref = $request->input('ref', 'POS-'.now()->format('Ymd-His'));

        $cfg = $this->resolveDarajaConfig($this->currentTenant($request));

        if (empty($cfg['consumerKey']) || empty($cfg['consumerSecret'])) {
            return response()->json([
                'success' => false,
                'message' => 'Daraja Consumer Key or Consumer Secret is not configured. Please set them in Settings or .env.',
            ], 422);
        }

        $token = $this->getAccessToken($cfg);

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Could not authenticate with Safaricom Daraja. Please check that your Consumer Key & Secret are valid.',
            ], 503);
        }

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($cfg['shortcode'].$cfg['passkey'].$timestamp);

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$cfg['baseUrl']}/mpesa/stkpush/v1/processrequest", [
                    'BusinessShortCode' => $cfg['shortcode'],
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => $cfg['transactionType'],
                    'Amount' => $amount,
                    'PartyA' => $phone,
                    'PartyB' => $cfg['shortcode'],
                    'PhoneNumber' => $phone,
                    'CallBackURL' => $cfg['callbackUrl'],
                    'AccountReference' => substr($ref, 0, 12),
                    'TransactionDesc' => substr($cfg['accountReference'].' Sale', 0, 20),
                ]);

            $data = $response->json();

            Log::info('M-Pesa STK Push sent', compact('phone', 'amount', 'ref') + ['response' => $data]);

            if ($response->successful() && ($data['ResponseCode'] ?? '') === '0') {
                $checkoutId = $data['CheckoutRequestID'] ?? null;
                if ($checkoutId) {
                    Cache::put("mpesa_cfg_{$checkoutId}", $cfg, now()->addMinutes(15));
                }

                return response()->json([
                    'success' => true,
                    'message' => 'STK Push sent. Awaiting customer PIN.',
                    'checkout_request_id' => $checkoutId,
                    'merchant_request_id' => $data['MerchantRequestID'] ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $data['errorMessage'] ?? ($data['ResponseDescription'] ?? 'Daraja returned an error.'),
                'daraja_code' => $data['ResponseCode'] ?? $data['errorCode'] ?? null,
            ], 422);

        } catch (\Throwable $e) {
            Log::error('M-Pesa STK Push exception', ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        }
    }

    // ─── STK Query ─────────────────────────────────────────────────────────────

    /**
     * POST /mpesa/stk-query
     * Body: { checkout_request_id }
     */
    public function stkQuery(Request $request): JsonResponse
    {
        $request->validate(['checkout_request_id' => 'required|string']);

        $checkoutId = $request->input('checkout_request_id');

        // Check if Safaricom callback arrived and cached confirmation
        $cached = Cache::get("mpesa_stk_{$checkoutId}");
        if ($cached && ! empty($cached['confirmed'])) {
            return response()->json([
                'success' => true,
                'confirmed' => true,
                'code' => $cached['code'] ?? null,
                'amount' => $cached['amount'] ?? null,
                'phone' => $cached['phone'] ?? null,
                'result_code' => 0,
                'description' => 'The service request is processed successfully.',
            ]);
        }

        if ($cached && isset($cached['confirmed']) && ! $cached['confirmed']) {
            return response()->json([
                'success' => true,
                'confirmed' => false,
                'result_code' => $cached['result_code'] ?? 1,
                'description' => $cached['description'] ?? 'Transaction was not completed.',
            ]);
        }

        $cfg = Cache::get("mpesa_cfg_{$checkoutId}") ?? $this->resolveDarajaConfig($this->currentTenant($request));
        $token = $this->getAccessToken($cfg);
        if (! $token) {
            return response()->json(['success' => false, 'message' => 'Auth failed.'], 503);
        }

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($cfg['shortcode'].$cfg['passkey'].$timestamp);

        try {
            $response = Http::withToken($token)
                ->timeout(20)
                ->post("{$cfg['baseUrl']}/mpesa/stkpushquery/v1/query", [
                    'BusinessShortCode' => $cfg['shortcode'],
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'CheckoutRequestID' => $checkoutId,
                ]);

            $data = $response->json();
            $resultCode = $data['ResultCode'] ?? null;
            $confirmed = ($resultCode === 0 || $resultCode === '0');

            Log::info('M-Pesa STK Query', $data);

            return response()->json([
                'success' => true,
                'confirmed' => $confirmed,
                'code' => $cached['code'] ?? null,
                'result_code' => $resultCode,
                'description' => $data['ResultDesc'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('M-Pesa STK Query exception', ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        }
    }

    // ─── Callback ──────────────────────────────────────────────────────────────

    /**
     * POST /mpesa/callback  (CSRF-exempt – see Http/Middleware/VerifyCsrfToken.php)
     * Safaricom POSTs the result here after the customer enters their PIN.
     */
    public function callback(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('M-Pesa Callback received', $payload);

        try {
            $body = $payload['Body']['stkCallback'] ?? [];
            $resultCode = $body['ResultCode'] ?? null;
            $checkoutId = $body['CheckoutRequestID'] ?? null;
            $items = $body['CallbackMetadata']['Item'] ?? [];

            $meta = [];
            foreach ($items as $item) {
                $meta[$item['Name']] = $item['Value'] ?? null;
            }

            if ($resultCode === 0) {
                Cache::put("mpesa_stk_{$checkoutId}", [
                    'confirmed' => true,
                    'code' => $meta['MpesaReceiptNumber'] ?? null,
                    'amount' => $meta['Amount'] ?? null,
                    'phone' => $meta['PhoneNumber'] ?? null,
                ], now()->addMinutes(15));

                Log::info('M-Pesa payment confirmed', [
                    'checkout_id' => $checkoutId,
                    'receipt' => $meta['MpesaReceiptNumber'] ?? 'N/A',
                    'amount' => $meta['Amount'] ?? 0,
                    'phone' => $meta['PhoneNumber'] ?? 'N/A',
                ]);
            } else {
                Cache::put("mpesa_stk_{$checkoutId}", [
                    'confirmed' => false,
                    'result_code' => $resultCode,
                    'description' => $body['ResultDesc'] ?? 'Transaction was not completed.',
                ], now()->addMinutes(15));

                Log::warning('M-Pesa payment failed/cancelled', [
                    'checkout_id' => $checkoutId,
                    'result_code' => $resultCode,
                    'description' => $body['ResultDesc'] ?? '',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('M-Pesa Callback parse error', ['message' => $e->getMessage()]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        } elseif (strlen($phone) === 9 && (str_starts_with($phone, '7') || str_starts_with($phone, '1'))) {
            $phone = '254'.$phone;
        }

        return $phone;
    }
}
