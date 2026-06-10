<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;

class MemoryController extends Controller
{
    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly StorageService   $storage,
    ) {}

    public function index(): JsonResponse
    {
        $list = $this->firestore->getCollection('memories', ['orderBy' => 'id', 'direction' => 'asc']);
        $list = $this->storage->resolveCollection($list, ['img']);
        return response()->json($list);
    }
}
