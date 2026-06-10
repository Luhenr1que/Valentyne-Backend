<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Paginates;
use App\Services\FirestoreService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BirthdayController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly StorageService   $storage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $all = Cache::remember('collection:birthday', 120, function () {
            $list = $this->firestore->getCollection('birthday', ['orderBy' => 'id', 'direction' => 'asc']);
            return $this->storage->resolveCollection($list, ['img']);
        });

        return response()->json($this->paginate($all, $request));
    }

    public function unlock(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return response()->json(['error' => 'ids é obrigatório'], 400);
        }

        foreach ($ids as $id) {
            $this->firestore->updateDocument('birthday', (string) $id, ['secret' => true]);
        }

        Cache::forget('collection:birthday');

        return response()->json(['success' => true, 'unlocked' => $ids]);
    }
}
