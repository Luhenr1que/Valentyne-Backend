<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TravelController extends Controller
{
    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly StorageService   $storage,
    ) {}

    public function index(): JsonResponse
    {
        $list = Cache::remember('collection:travel', 120, function () {
            $list = $this->firestore->getCollection('travel', ['orderBy' => 'id', 'direction' => 'asc']);
            return $this->storage->resolveCollection($list, ['img']);
        });

        return response()->json($list);
    }
}
