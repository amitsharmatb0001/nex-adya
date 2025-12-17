<?php

namespace App\Services\Fraud;

use Illuminate\Support\Facades\Http;

class FraudMLService
{
    /**
     * Call external ML service for anomaly scoring.
     */
    public function score(array $context): ?float
    {
        try {
            $response = Http::timeout(2)
                ->post(config('fraud.ml_url') . '/score', $context);

            if ($response->ok()) {
                $data = $response->json();
                if ($data['prediction'] === 'fraud') {
                    return 1.0; // high risk
                }
                return max(0, min(1, 1 - $data['score'])); // normalize
            }
        } catch (\Throwable $e) {
            \Log::warning("Fraud ML scoring failed: {$e->getMessage()}");
        }

        return null; // fallback to rules only
    }
}
