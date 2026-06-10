<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class FirestoreService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $project = config('firebase.project_id');
        $this->baseUrl = "https://firestore.googleapis.com/v1/projects/{$project}/databases/(default)/documents";
        $this->apiKey  = config('firebase.api_key');
    }

    public function getCollection(string $collection, array $options = []): array
    {
        $url = "{$this->baseUrl}/{$collection}";

        $params = ['key' => $this->apiKey];
        if (isset($options['pageSize'])) {
            $params['pageSize'] = $options['pageSize'];
        }

        $response = Http::get($url, $params);
        $this->assertOk($response, "getCollection({$collection})");

        $documents = $response->json('documents') ?? [];

        $items = array_map(fn($doc) => $this->parseDocument($doc), $documents);

        if (isset($options['orderBy'])) {
            $field     = $options['orderBy'];
            $direction = $options['direction'] ?? 'asc';
            usort($items, function ($a, $b) use ($field, $direction) {
                $va = $a[$field] ?? 0;
                $vb = $b[$field] ?? 0;
                return $direction === 'desc' ? $vb <=> $va : $va <=> $vb;
            });
        }

        if (isset($options['where'])) {
            foreach ($options['where'] as [$field, $op, $value]) {
                $items = array_values(array_filter($items, function ($item) use ($field, $op, $value) {
                    return match ($op) {
                        '=='    => ($item[$field] ?? null) === $value,
                        '!='    => ($item[$field] ?? null) !== $value,
                        '>'     => ($item[$field] ?? null) > $value,
                        '>='    => ($item[$field] ?? null) >= $value,
                        '<'     => ($item[$field] ?? null) < $value,
                        '<='    => ($item[$field] ?? null) <= $value,
                        default => true,
                    };
                }));
            }
        }

        return $items;
    }

    public function createDocument(string $collection, array $data, ?string $docId = null): string
    {
        $url    = "{$this->baseUrl}/{$collection}";
        $params = ['key' => $this->apiKey];

        if ($docId !== null) {
            $url    .= "/{$docId}";
            $params['currentDocument.exists'] = 'false';
            $response = Http::patch($url . '?' . http_build_query(['key' => $this->apiKey]),
                ['fields' => $this->encodeFields($data)]
            );
        } else {
            $response = Http::post($url . '?' . http_build_query($params),
                ['fields' => $this->encodeFields($data)]
            );
        }

        $this->assertOk($response, "createDocument({$collection})");

        $name = $response->json('name');
        return basename($name);
    }

    public function deleteDocument(string $collection, string $docId): void
    {
        $url      = "{$this->baseUrl}/{$collection}/{$docId}";
        $response = Http::delete($url . '?' . http_build_query(['key' => $this->apiKey]));
        $this->assertOk($response, "deleteDocument({$collection}/{$docId})");
    }

    public function setDocument(string $collection, string $docId, array $data): void
    {
        $url      = "{$this->baseUrl}/{$collection}/{$docId}";
        $response = Http::patch($url . '?' . http_build_query(['key' => $this->apiKey]),
            ['fields' => $this->encodeFields($data)]
        );
        $this->assertOk($response, "setDocument({$collection}/{$docId})");
    }

    public function updateDocument(string $collection, string $docId, array $data): void
    {
        $fieldPaths = implode('&', array_map(
            fn($k) => 'updateMask.fieldPaths=' . urlencode($k),
            array_keys($data)
        ));

        $url = "{$this->baseUrl}/{$collection}/{$docId}?key={$this->apiKey}&{$fieldPaths}";

        $response = Http::patch($url, ['fields' => $this->encodeFields($data)]);
        $this->assertOk($response, "updateDocument({$collection}/{$docId})");
    }

    public function getMaxId(string $collection, string $field = 'id'): int
    {
        $items = $this->getCollection($collection, ['orderBy' => $field, 'direction' => 'desc', 'pageSize' => 1]);
        return isset($items[0][$field]) ? (int) $items[0][$field] : 0;
    }

    // ── Firestore value codec ────────────────────────────────────────────────

    private function parseDocument(array $doc): array
    {
        $parsed = ['docId' => basename($doc['name'])];
        foreach ($doc['fields'] ?? [] as $key => $value) {
            $parsed[$key] = $this->decodeValue($value);
        }
        return $parsed;
    }

    private function decodeValue(array $value): mixed
    {
        if (isset($value['stringValue']))    return $value['stringValue'];
        if (isset($value['integerValue']))   return (int) $value['integerValue'];
        if (isset($value['doubleValue']))    return (float) $value['doubleValue'];
        if (isset($value['booleanValue']))   return (bool) $value['booleanValue'];
        if (isset($value['nullValue']))      return null;
        if (isset($value['timestampValue'])) return $value['timestampValue'];
        if (isset($value['arrayValue'])) {
            return array_map(
                fn($v) => $this->decodeValue($v),
                $value['arrayValue']['values'] ?? []
            );
        }
        if (isset($value['mapValue'])) {
            $result = [];
            foreach ($value['mapValue']['fields'] ?? [] as $k => $v) {
                $result[$k] = $this->decodeValue($v);
            }
            return $result;
        }
        return null;
    }

    private function encodeFields(array $data): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[$key] = $this->encodeValue($value);
        }
        return $fields;
    }

    private function encodeValue(mixed $value): array
    {
        return match (true) {
            is_null($value)   => ['nullValue' => null],
            is_bool($value)   => ['booleanValue' => $value],
            is_int($value)    => ['integerValue' => (string) $value],
            is_float($value)  => ['doubleValue' => $value],
            is_string($value) => ['stringValue' => $value],
            is_array($value) && array_is_list($value) => [
                'arrayValue' => ['values' => array_map(fn($v) => $this->encodeValue($v), $value)],
            ],
            is_array($value) => [
                'mapValue' => ['fields' => $this->encodeFields($value)],
            ],
            $value instanceof \DateTimeInterface => ['timestampValue' => $value->format(\DateTime::RFC3339_EXTENDED)],
            default => ['stringValue' => (string) $value],
        };
    }

    private function assertOk(Response $response, string $context): void
    {
        if (!$response->successful()) {
            throw new \RuntimeException(
                "Firebase Firestore error in {$context}: " . $response->body()
            );
        }
    }
}
