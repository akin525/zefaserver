<?php

namespace App\Services;

use App\Models\User;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class CashonrailsService
{
    protected string $secretKey;
    protected string $baseUrl;
    protected int $timeout;
    public function __construct()
    {
        $this->secretKey = config('services.cashonrails.secret_key');
        $this->baseUrl = config('services.cashonrails.base_url');
        $this->timeout = config('services.cashonrails.timeout', 30);

        $this->validateConfiguration();
    }

    public function makePayout(Withdrawal $withdrawal): array
    {

        if($withdrawal->status != 'pending'){
            return [
                'status' => false,
                'message' => 'ERROR:'.'Withdrawal already processed'
            ];
        }

        try {

            $payload = [
                'account_number' => $withdrawal->bankAccount->account_number,
                'account_name' => $withdrawal->bankAccount->account_name,
                'bank_code' => $withdrawal->bankAccount->bank_code,
                'amount' => $withdrawal->amount,
                'currency' => 'NGN',
                'sender_name' => $withdrawal->bankAccount->account_name,
                'narration' => 'Sent from Cashon',
                'reference' => $withdrawal->reference
            ];

            Log::info('Cashonrails: Initiating makePayout', $payload);

            $signature = hash_hmac('sha512', json_encode($payload), $this->secretKey);

            $response = Http::timeout($this->timeout)
                ->withHeaders(array_merge($this->getRequestHeaders(), ['Signature' => $signature]))
                ->withOptions(['verify' => false])
                ->post("{$this->baseUrl}/bank_transfer", $payload);

            $data = $response->json();

            Log::notice('Cashonrails: makePayout response received', [
                'status_code' => $response->status(),
                'has_data' => $data
            ]);

            if (!$data['success']) {
                return [
                    'success' => false,
                    'message' => $data['message'] ?? 'Unknown API error occurred'
                ];
            }


            Log::info('Cashonrails: makePayout response received', [
                'status_code' => $response->status(),
                'has_data' => isset($data['data'])
            ]);


            return [
                'success' => true,
                'data' => $data['data']
            ];

        } catch (Exception $exception) {
            Log::error('Cashonrails: makePayout failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'ERROR:'. $exception->getMessage()
            ];
        }
    }

    public function createVirtualAccount(User $user): array
    {
        $customerCode = $this->createCustomer($user);

        if(str_contains($customerCode,'ERROR')) {
            return [
                'success' => false,
                'message' => $customerCode
            ];
        }
        try {

            $payload = [
                'customer_code' => $customerCode,
                'provider' => env('CASHONRAILS_DEFAULT_BANK',"vfd"),
                'id' => $user->id
            ];

            Log::info('Cashonrails: Initiating createVirtualAccount', $payload);

            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getRequestHeaders())
                ->withOptions(['verify' => false])
                ->post("{$this->baseUrl}/reserved_virtual_account", $payload);

            $data = $response->json();

            Log::notice('Cashonrails: createVirtualAccount response received', [
                'status_code' => $response->status(),
                'has_data' => $data
            ]);

            if (!$data['success']) {
                return [
                    'success' => false,
                    'message' => $data['message'] ?? 'Unknown API error occurred'
                ];
            }


            Log::info('Cashonrails: createVirtualAccount response received', [
                'status_code' => $response->status(),
                'has_data' => isset($data['data'])
            ]);


            return [
                'success' => true,
                'data' => $data['data']
            ];

        } catch (Exception $exception) {
            Log::error('Cashonrails: createVirtualAccount failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $customerCode
            ];
        }
    }

    public function createCustomer(User $user): string
    {
        try {

            Log::info($user->verification_number);
            $payload = [
                "email"=> $user->email,
                "first_name" => $user->first_name,
                "last_name" => $user->last_name,
                "phone"=> $user->phone,
                "dob"=> Carbon::parse($user->dob)->format('Y-m-d'),
                "bvn"=> $user->verification_type == "bvn" ? $user->verification_number : "",
                "nin"=> $user->verification_type == "nin" ? $user->verification_number : ""
            ];

            Log::info('Cashonrails: Initiating createCustomer', $payload);

            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getRequestHeaders())
                ->withOptions(['verify' => false])
                ->post("{$this->baseUrl}/customer", $payload);

            $data = $response->json();

            Log::notice('Cashonrails: createCustomer response received', $data);

            if (!$data['success']) {
                throw new Exception("ERROR:".$data['message'] ?? 'Unknown API error occurred');
            }

            return $data['data']['customer_code'];

        } catch (Exception $exception) {
            Log::error('Cashonrails: createCustomer validation failed', (array)$exception->getMessage());

            throw new Exception("ERROR:".$exception->getMessage());
        }
    }

    public function getBankList(): array
    {
        try {
            Log::info('Cashonrails: Initiating bank list retrieval');

            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getRequestHeaders())
                ->get("{$this->baseUrl}/bank_list/NGN");

            if (!$response->successful()) {
                throw new Exception(
                    "API request failed with status: {$response->status()}"
                );
            }

            $data = $response->json();

            Log::info('Cashonrails: API response received', [
                'status_code' => $response->status(),
                'has_data' => isset($data['data'])
            ]);

            return $this->processApiResponse($data);

        } catch (Exception $exception) {
            Log::error('Cashonrails: Bank list retrieval failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);

            throw $exception;
        }
    }

    public function validateAccountName(string $accountNumber, string $bankCode, string $currency = 'NGN'): array
    {
        try {
            $this->validateAccountNameParameters($accountNumber, $bankCode, $currency);

            Log::info('Cashonrails: Initiating account name validation', [
                'account_number' => $this->maskAccountNumber($accountNumber),
                'bank_code' => $bankCode,
                'currency' => $currency
            ]);

            $payload = [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'currency' => strtoupper($currency)
            ];


            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getRequestHeaders())
                ->post("{$this->baseUrl}/account_name", $payload);

            $data = $response->json();

            Log::notice('Cashonrails: Api response received', [
                'status_code' => $response->status(),
                'has_data' => $data,
                'account_number' => $this->maskAccountNumber($accountNumber)
            ]);

            if (!$data['success']) {
                throw new Exception(
                    "Account validation request failed with status: {$response->status()}"
                );
            }


            Log::info('Cashonrails: Account validation response received', [
                'status_code' => $response->status(),
                'has_data' => isset($data['data']),
                'account_number' => $this->maskAccountNumber($accountNumber)
            ]);

            return $this->processAccountValidationResponse($data, $accountNumber);

        } catch (Exception $exception) {
            Log::error('Cashonrails: Account name validation failed', [
                'account_number' => $this->maskAccountNumber($accountNumber),
                'bank_code' => $bankCode,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);

            throw $exception;
        }
    }

    protected function validateAccountNameParameters(string $accountNumber, string $bankCode, string $currency): void
    {
        if (empty($accountNumber)) {
            throw new Exception('Account number is required');
        }

        if (empty($bankCode)) {
            throw new Exception('Bank code is required');
        }

        if (empty($currency)) {
            throw new Exception('Currency is required');
        }

        // Validate account number format (typically 10 digits for Nigerian banks)
        if (!preg_match('/^\d{10}$/', $accountNumber)) {
            throw new Exception('Account number must be exactly 10 digits');
        }

        // Validate bank code format (typically 6 digits)
        if (!preg_match('/^\d{6}$/', $bankCode)) {
            throw new Exception('Bank code must be exactly 6 digits');
        }

        // Validate currency (only NGN supported for now)
        $supportedCurrencies = ['NGN'];
        if (!in_array(strtoupper($currency), $supportedCurrencies)) {
            throw new Exception('Currency must be one of: ' . implode(', ', $supportedCurrencies));
        }
    }


    protected function processAccountValidationResponse(array $data, string $accountNumber): array
    {
        if (!isset($data['success']) || $data['success'] !== true) {
            $errorMessage = $data['message'] ?? 'Account validation failed';
            throw new Exception("API returned error: {$errorMessage}");
        }

        if (!isset($data['data'])) {
            throw new Exception('API response missing account data');
        }

        $accountData = $data['data'];

        // Validate that we have the required account information
//        if (!isset($accountData['data'])) {
//            throw new Exception('Account name not found in API response');
//        }

        Log::info('Cashonrails: Account validation successful', [
            'account_number' => $this->maskAccountNumber($accountNumber),
            'account_name' => $accountData['data'] ?? 'N/A'
        ]);

        return [
            'status' => true,
            'data' => [
                'account_number' => $accountNumber,
                'account_name' => $data['data'],
                'bank_code' => $accountData['bank_code'] ?? null,
                'bank_name' => $accountData['bank_name'] ?? null,
                'currency' => $accountData['currency'] ?? 'NGN'
            ],
            'message' => 'Account validation successful'
        ];
    }
    protected function maskAccountNumber(string $accountNumber): string
    {
        if (strlen($accountNumber) <= 4) {
            return str_repeat('*', strlen($accountNumber));
        }

        return substr($accountNumber, 0, 2) . str_repeat('*', strlen($accountNumber) - 4) . substr($accountNumber, -2);
    }
    protected function processApiResponse(array $data): array
    {
        if (!isset($data['success']) || $data['success'] !== true) {
            $errorMessage = $data['message'] ?? 'Unknown API error occurred';
            throw new Exception("API returned error: {$errorMessage}");
        }

        if (!isset($data['data'])) {
            throw new Exception('API response missing data field');
        }

        Log::info('Cashonrails: Bank list retrieved successfully', [
            'bank_count' => is_array($data['data']) ? count($data['data']) : 0
        ]);

        return [
            'status' => true,
            'data' => $data['data'],
            'message' => 'Bank list retrieved successfully'
        ];
    }


    protected function getRequestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->secretKey}",
            'User-Agent' => 'Laravel-App/' . app()->version()
        ];
    }
    protected function validateConfiguration(): void
    {
        if (empty($this->secretKey)) {
            throw new Exception('Cashonrails secret key is not configured');
        }

        if (empty($this->baseUrl)) {
            throw new Exception('Cashonrails base URL is not configured');
        }

        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new Exception('Cashonrails base URL is not valid');
        }
    }

    public function getConfiguration(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'has_secret_key' => !empty($this->secretKey)
        ];
    }
}
