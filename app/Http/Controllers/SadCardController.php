<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SadCardController extends Controller
{
    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly StorageService   $storage,
    ) {}

    public function index(): JsonResponse
    {
        $list = Cache::remember('collection:sad-cards', 120, function () {
            $list = $this->firestore->getCollection('sadCards', ['orderBy' => 'id', 'direction' => 'desc']);
            return $this->storage->resolveCollection($list, ['image', 'img']);
        });

        return response()->json($list);
    }
}
