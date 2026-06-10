<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    public function __construct(private readonly FirestoreService $firestore) {}

    public function send(string $title, string $body, array $data = []): void
    {
        $tokens = $this->firestore->getCollection('push_tokens');

        $expoPushTokens = array_filter(
            array_column($tokens, 'token'),
            fn($t) => str_starts_with($t, 'ExponentPushToken[')
        );

        if (empty($expoPushTokens)) {
            return;
        }

        $messages = array_values(array_map(fn($token) => [
            'to'    => $token,
            'title' => $title,
            'body'  => $body,
            'data'  => $data,
            'sound' => 'default',
        ], $expoPushTokens));

        $chunks = array_chunk($messages, 100);

        foreach ($chunks as $chunk) {
            $response = Http::withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->post(self::EXPO_PUSH_URL, $chunk);

            if (!$response->successful()) {
                Log::error('Expo push failed', ['body' => $response->body()]);
            }
        }
    }
}
