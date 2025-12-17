<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PushService
{
    protected string $fcmKey;

    public function __construct()
    {
        $this->fcmKey = config('services.fcm.server_key');
    }

    public function sendToToken(string $deviceToken, array $payload): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'key=' . $this->fcmKey,
            'Content-Type'  => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', [
            'to' => $deviceToken,
            'notification' => [
                'title' => $payload['title'] ?? 'Notification',
                'body'  => $payload['body'] ?? '',
            ],
            'data' => $payload['data'] ?? [],
        ]);

        return $response->successful();
    }

    public function sendToUser(int $userId, array $payload): bool
    {
        // Example: store device tokens in a table `user_devices`
        $tokens = \DB::table('user_devices')->where('user_id', $userId)->pluck('fcm_token');
        foreach ($tokens as $token) {
            $this->sendToToken($token, $payload);
        }
        return true;
    }
}
