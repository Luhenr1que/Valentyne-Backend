<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AppVersionController extends Controller
{
    public function __construct(private readonly FirestoreService $firestore) {}

    public function index(): JsonResponse
    {
        $data = Cache::remember('app:version', 60, function () {
            $docs = $this->firestore->getCollection('apk_version', [
                'orderBy'   => 'created_at',
                'direction' => 'desc',
                'pageSize'  => 20,
            ]);

            if (empty($docs)) return null;

            usort($docs, fn($a, $b) => version_compare($b['version'] ?? '0', $a['version'] ?? '0'));

            return $docs[0];
        });

        if (!$data) {
            return response()->json(['version' => '1.0.0', 'url' => null, 'force' => false]);
        }

        return response()->json([
            'version' => $data['version'] ?? '1.0.0',
            'url'     => $data['url']     ?? null,
            'force'   => (bool) ($data['force'] ?? false),
            'notes'   => $data['notes']   ?? null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $version = $request->input('version');
        $url     = $request->input('url');
        $force   = $request->boolean('force', false);
        $notes   = $request->input('notes', '');

        if (!$version || !$url) {
            return response()->json(['error' => 'version e url são obrigatórios'], 422);
        }

        $this->firestore->createDocument('apk_version', [
            'version'    => $version,
            'url'        => $url,
            'force'      => $force,
            'notes'      => $notes,
            'created_at' => time(),
        ]);

        Cache::forget('app:version');

        return response()->json(['ok' => true, 'version' => $version]);
    }
}
