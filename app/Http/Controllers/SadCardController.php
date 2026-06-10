<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;

class SadCardController extends Controller
{
    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly StorageService   $storage,
    ) {}

    public function index(): JsonResponse
    {
        $list = $this->firestore->getCollection('sadCards', ['orderBy' => 'id', 'direction' => 'asc']);
        $list = $this->storage->resolveCollection($list, ['img']);
        return response()->json($list);
    }
}
