<?php

namespace App\Services;

use App\Models\VerificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SprintCheckService
{
    protected $apiKey;
    protected $encryptionKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('SPRINTCHECK_API_KEY');
        $this->encryptionKey = env('SPRINTCHECK_ENCRYPTION_KEY');
        $this->baseUrl = env('SPRINTCHECK_BASE_URL');
    }

    public function verifyBusiness($name)
    {
        $payload = [
            'name' => $name,
        ];

        return $this->sendRequest('/cac/name', 'CAC', $payload);
    }
    public function verifyBVN($bvn, $identifier)
    {
        $payload = [
            'number' => $bvn,
            'identifier' => $identifier,
        ];

        return $this->sendRequest('/bvn', 'bvn', $payload);
    }

    public function verifyNIN($nin, $identifier)
    {
        $payload = [
            'number' => $nin,
            'identifier' => $identifier,
        ];

        return $this->sendRequest('/nin', 'nin', $payload);
    }

    public function verifyVoters($voters, $identifier)
    {
        $payload = [
            'number' => $voters,
            'identifier' => $identifier,
        ];

        return $this->sendRequest('/voters', 'voters', $payload);
    }

    public function verifyPassport($firstname, $lastname, $dob, $number, $identifier)
    {
        $payload = [
            'first_name' => $firstname,
            'last_name' => $lastname,
            'dob' => $dob,
            'number' => $number,
            'identifier' => $identifier,
        ];

        return $this->sendRequest('/passport', 'passport', $payload);
    }

    public function verifyDriverLicense($firstname, $lastname, $dob, $number, $identifier)
    {
        $payload = [
            'first_name' => $firstname,
            'last_name' => $lastname,
            'dob' => $dob,
            'number' => $number,
            'identifier' => $identifier,
        ];

        return $this->sendRequest('/drivers-license', 'drivers-license', $payload);
    }

    protected function sendRequest($endpoint, $verificationType, array $payload)
    {
        $verificationLog = null;

        try {
            // Start database transaction
            DB::beginTransaction();

            Log::info("Request sent to SprintCheck", [
                'type' => $verificationType,
                'request' => $payload
            ]);

            // Create initial verification log
            $verificationLog = VerificationLog::create([
                'user_id' => auth()->id(),
                'verification_type' => $verificationType,
                'verification_number' => $payload['number'] || $payload['name'] ?? null,
                'identifier' => $payload['identifier'] ?? null,
                'request_payload' => $payload,
                'response_data' => [],
                'verification_success' => false,
                'status_message' => 'Processing...',
            ]);

            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $signature = hash_hmac('sha512', $jsonPayload, $this->encryptionKey);

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => $this->apiKey,
                'signature' => $signature,
            ])->post("{$this->baseUrl}{$endpoint}", $payload);

            $data = $response->json();

            Log::info("SprintCheck response", [
                'type' => $verificationType,
                'response' => $data
            ]);

            $isSuccess = isset($data['success']) && $data['success'] == 1;

            // Update verification log with response
            $verificationLog->update([
                'response_data' => $data,
                'verification_success' => $isSuccess,
                'status_message' => $data['message'] ?? ($isSuccess ? 'Verification successful' : 'Verification failed'),
                'reference_id' => $data['reference'] ?? $data['ref'] ?? null,
                'cost' => $data['cost'] ?? null,
                'verified_at' => $isSuccess ? now() : null,
            ]);

            if ($isSuccess) {

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => $data['message'] ?? 'Verification successful.',
                    'data' => $data['data'] ?? [],
                    'verification_id' => $verificationLog->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => false,
                'message' => $data['message'] ?? 'Verification failed.',
                'data' => $data['data'] ?? [],
                'verification_id' => $verificationLog->id,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::critical('SprintCheck API request failed.', [
                'type' => $verificationType,
                'error' => $e->getMessage(),
                'request' => $payload,
            ]);

            // Update verification log with error if it exists
            if ($verificationLog) {
                $verificationLog->update([
                    'verification_success' => false,
                    'status_message' => 'API request failed: ' . $e->getMessage(),
                    'response_data' => ['error' => $e->getMessage()],
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
                'verification_id' => $verificationLog?->id,
            ], 500);
        }
    }

    /**
     * Get verification history for a user
     */
    public function getVerificationHistory($userId = null, $type = null)
    {
        $query = VerificationLog::with('user')
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($type, fn($q) => $q->where('verification_type', $type))
            ->orderBy('created_at', 'desc');

        return $query->get();
    }

    /**
     * Get verification statistics
     */
    public function getVerificationStats($userId = null)
    {
        $query = VerificationLog::query()
            ->when($userId, fn($q) => $q->where('user_id', $userId));

        return [
            'total_verifications' => $query->count(),
            'successful_verifications' => $query->where('verification_success', true)->count(),
            'failed_verifications' => $query->where('verification_success', false)->count(),
            'total_cost' => $query->sum('cost'),
            'by_type' => $query->groupBy('verification_type')
                ->selectRaw('verification_type, count(*) as count, sum(cost) as total_cost')
                ->get()
                ->keyBy('verification_type'),
        ];
    }
}
