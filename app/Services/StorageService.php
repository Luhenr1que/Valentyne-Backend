<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class StorageService
{
    private string $bucket;
    private string $apiKey;

    public function __construct()
    {
        $this->bucket = config('firebase.storage_bucket');
        $this->apiKey = config('firebase.api_key');
    }

    public function resolveUrl(?string $path): ?string
    {
        if (blank($path)) return null;
        if (str_starts_with($path, 'http')) return $path;

        $encoded  = rawurlencode($path);
        $url      = "https://firebasestorage.googleapis.com/v0/b/{$this->bucket}/o/{$encoded}";

        $response = Http::get($url, ['key' => $this->apiKey]);

        if (!$response->successful()) {
            logger()->warning("StorageService: could not resolve {$path}", ['status' => $response->status()]);
            return null;
        }

        $tokens = $response->json('downloadTokens') ?? '';
        $token  = explode(',', $tokens)[0] ?? '';

        return "{$url}?alt=media&token={$token}";
    }

    public function resolveFields(array $item, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($item[$field])) {
                $item[$field] = $this->resolveUrl($item[$field]);
            }
        }
        return $item;
    }

    public function resolveCollection(array $items, array $fields): array
    {
        return array_map(fn($item) => $this->resolveFields($item, $fields), $items);
    }
}
