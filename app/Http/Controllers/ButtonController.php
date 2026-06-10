<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Paginates;
use App\Services\FirestoreService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ButtonController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly StorageService   $storage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active');
        $cacheKey   = 'collection:buttons' . ($activeOnly ? ':active' : '');

        $all = Cache::remember($cacheKey, 120, function () use ($activeOnly) {
            $options = $activeOnly ? ['where' => [['active', '==', true]]] : [];
            $list    = $this->firestore->getCollection('bottons', $options);
            return $this->storage->resolveCollection($list, ['img']);
        });

        return response()->json($this->paginate($all, $request));
    }
}
