<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Paginates;
use App\Services\FirestoreService;
use App\Services\LinkPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly FirestoreService   $firestore,
        private readonly LinkPreviewService $preview,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $all = $this->firestore->getCollection('wishlist', [
            'orderBy'   => 'createdAt',
            'direction' => 'desc',
        ]);

        return response()->json($this->paginate($all, $request));
    }

    public function store(Request $request): JsonResponse
    {
        $url = trim((string) $request->input('url', ''));

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Link inválido'], 422);
        }

        $preview = $this->preview->fetch($url);

        $data = array_merge($preview, [
            'id'        => $this->firestore->getMaxId('wishlist') + 1,
            'createdAt' => now()->toIso8601String(),
        ]);

        $docId = $this->firestore->createDocument('wishlist', $data);

        return response()->json(array_merge($data, ['docId' => $docId]), 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->firestore->deleteDocument('wishlist', $id);

        return response()->json(['ok' => true]);
    }
}
