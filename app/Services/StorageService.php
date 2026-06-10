<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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

        return Cache::remember("storage:{$path}", 3600, function () use ($path) {
            $encoded  = rawurlencode($path);
            $url      = "https://firebasestorage.googleapis.com/v0/b/{$this->bucket}/o/{$encoded}";
            $response = Http::get($url, ['key' => $this->apiKey]);

            if (!$response->successful()) return null;

            $token = explode(',', $response->json('downloadTokens') ?? '')[0] ?? '';
            return "{$url}?alt=media&token={$token}";
        });
    }

    public function resolveCollection(array $items, array $fields): array
    {
        $paths = [];
        foreach ($items as $item) {
            foreach ($fields as $field) {
                $path = $item[$field] ?? null;
                if ($path && !str_starts_with($path, 'http') && !isset($paths[$path])) {
                    $paths[$path] = Cache::get("storage:{$path}");
                }
            }
        }

        $unresolved = array_keys(array_filter($paths, fn($v) => $v === null));

        if (!empty($unresolved)) {
            $responses = Http::pool(function ($pool) use ($unresolved) {
                return array_map(fn($path) => $pool->as($path)->get(
                    "https://firebasestorage.googleapis.com/v0/b/{$this->bucket}/o/" . rawurlencode($path),
                    ['key' => $this->apiKey]
                ), $unresolved);
            });

            foreach ($unresolved as $path) {
                $response = $responses[$path] ?? null;
                if ($response && $response->successful()) {
                    $token = explode(',', $response->json('downloadTokens') ?? '')[0] ?? '';
                    $encoded = rawurlencode($path);
                    $url = "https://firebasestorage.googleapis.com/v0/b/{$this->bucket}/o/{$encoded}?alt=media&token={$token}";
                    Cache::put("storage:{$path}", $url, 3600);
                    $paths[$path] = $url;
                }
            }
        }

        return array_map(function ($item) use ($fields, $paths) {
            foreach ($fields as $field) {
                $path = $item[$field] ?? null;
                if ($path && !str_starts_with($path, 'http')) {
                    $item[$field] = $paths[$path] ?? null;
                }
            }
            return $item;
        }, $items);
    }
}
