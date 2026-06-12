<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkPreviewService
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Busca um preview do link de forma híbrida:
     * 1) tenta ler as meta tags Open Graph diretamente;
     * 2) se vier incompleto (ex: Shopee), cai pro Microlink.
     */
    public function fetch(string $url): array
    {
        $store   = $this->detectStore($url);
        $preview = $this->scrapeOpenGraph($url);

        if (empty($preview['image']) || empty($preview['title'])) {
            $fallback = $this->fetchFromMicrolink($url);
            $preview = [
                'title' => $preview['title'] ?: ($fallback['title'] ?? null),
                'image' => $preview['image'] ?: ($fallback['image'] ?? null),
                'price' => $preview['price'] ?: ($fallback['price'] ?? null),
            ];
        }

        return [
            'url'   => $url,
            'title' => $preview['title'] ?? null,
            'image' => $preview['image'] ?? null,
            'price' => $preview['price'] ?? null,
            'store' => $store,
        ];
    }

    private function scrapeOpenGraph(string $url): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent'      => self::UA,
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
            ])->timeout(12)->get($url);

            if (!$response->successful()) {
                return [];
            }

            $html = $response->body();

            return [
                'title' => $this->meta($html, 'og:title') ?: $this->titleTag($html),
                'image' => $this->meta($html, 'og:image:secure_url') ?: $this->meta($html, 'og:image'),
                'price' => $this->meta($html, 'product:price:amount') ?: $this->meta($html, 'og:price:amount'),
            ];
        } catch (\Throwable $e) {
            Log::warning('LinkPreview OG scrape failed', ['url' => $url, 'error' => $e->getMessage()]);
            return [];
        }
    }

    private function fetchFromMicrolink(string $url): array
    {
        try {
            $response = Http::timeout(15)->get('https://api.microlink.io', ['url' => $url]);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json('data') ?? [];

            return [
                'title' => $data['title'] ?? null,
                'image' => $data['image']['url'] ?? ($data['logo']['url'] ?? null),
                'price' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('LinkPreview Microlink failed', ['url' => $url, 'error' => $e->getMessage()]);
            return [];
        }
    }

    private function meta(string $html, string $property): ?string
    {
        $quoted   = preg_quote($property, '/');
        $patterns = [
            '/<meta[^>]+(?:property|name)=["\']' . $quoted . '["\'][^>]+content=["\']([^"\']*)["\']/i',
            '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:property|name)=["\']' . $quoted . '["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m) && trim($m[1]) !== '') {
                return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    private function titleTag(string $html): ?string
    {
        if (preg_match('/<title[^>]*>([^<]*)<\/title>/i', $html, $m) && trim($m[1]) !== '') {
            return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    private function detectStore(string $url): string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        return match (true) {
            str_contains($host, 'shopee')                                                         => 'Shopee',
            str_contains($host, 'mercadolivre') || str_contains($host, 'mercadolibre')            => 'Mercado Livre',
            str_contains($host, 'amazon')                                                         => 'Amazon',
            str_contains($host, 'aliexpress')                                                     => 'AliExpress',
            str_contains($host, 'magazineluiza') || str_contains($host, 'magalu')                 => 'Magalu',
            str_contains($host, 'americanas')                                                     => 'Americanas',
            str_contains($host, 'shein')                                                          => 'Shein',
            str_contains($host, 'casasbahia')                                                     => 'Casas Bahia',
            default                                                                               => $host !== '' ? preg_replace('/^www\./', '', $host) : 'Link',
        };
    }
}
