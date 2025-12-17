<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toFcm')) {
            Log::warning('Notification does not implement toFcm method', [
                'notification' => get_class($notification)
            ]);
            return;
        }

        $message = $notification->toFcm($notifiable);
        
        if (!$message) {
            return;
        }

        // Get FCM tokens for the notifiable
        $tokens = $this->getFcmTokens($notifiable);
        
        if (empty($tokens)) {
            Log::debug('No FCM tokens found for notifiable', [
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? 'unknown'
            ]);
            return;
        }

        // Send to FCM
        $this->sendToFcm($tokens, $message);
    }

    /**
     * Get FCM tokens for the notifiable entity
     */
    protected function getFcmTokens($notifiable)
    {
        // Check if notifiable has fcm_tokens relationship or property
        if (method_exists($notifiable, 'fcmTokens')) {
            return $notifiable->fcmTokens()->pluck('token')->toArray();
        }

        // Check for direct fcm_token property
        if (property_exists($notifiable, 'fcm_token') && $notifiable->fcm_token) {
            return [$notifiable->fcm_token];
        }

        // Check for fcm_tokens property (array)
        if (property_exists($notifiable, 'fcm_tokens') && is_array($notifiable->fcm_tokens)) {
            return $notifiable->fcm_tokens;
        }

        return [];
    }

    /**
     * Send notification to FCM
     */
    protected function sendToFcm(array $tokens, array $message)
    {
        $serverKey = config('services.fcm.server_key');
        
        if (!$serverKey) {
            Log::error('FCM server key not configured');
            return;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $tokens,
                'notification' => $message['notification'] ?? null,
                'data' => $message['data'] ?? null,
                'priority' => $message['priority'] ?? 'high',
            ]);

            if ($response->successful()) {
                Log::info('FCM notification sent successfully', [
                    'tokens_count' => count($tokens),
                    'response' => $response->json()
                ]);
            } else {
                Log::error('FCM notification failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('FCM notification exception', [
                'error' => $e->getMessage(),
                'tokens_count' => count($tokens)
            ]);
        }
    }
}
