<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Paginates;
use App\Services\FirestoreService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SadCardController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly StorageService   $storage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $all = Cache::remember('collection:sad-cards', 120, function () {
            $list = $this->firestore->getCollection('sadCards', ['orderBy' => 'id', 'direction' => 'desc']);
            return $this->storage->resolveCollection($list, ['image', 'img']);
        });

        return response()->json($this->paginate($all, $request));
    }
}
