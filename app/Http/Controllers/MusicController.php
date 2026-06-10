<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;

class MusicController extends Controller
{
    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly StorageService   $storage,
    ) {}

    public function index(): JsonResponse
    {
        $list = $this->firestore->getCollection('music', ['orderBy' => 'id', 'direction' => 'desc']);
        $list = $this->storage->resolveCollection($list, ['song', 'img']);
        return response()->json($list);
    }
}
