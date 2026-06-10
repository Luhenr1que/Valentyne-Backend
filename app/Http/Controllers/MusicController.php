<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Paginates;
use App\Services\FirestoreService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MusicController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly StorageService   $storage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $all = Cache::remember('collection:music', 120, function () {
            $list = $this->firestore->getCollection('music', ['orderBy' => 'id', 'direction' => 'desc']);
            return $this->storage->resolveCollection($list, ['song', 'img']);
        });

        return response()->json($this->paginate($all, $request));
    }
}
