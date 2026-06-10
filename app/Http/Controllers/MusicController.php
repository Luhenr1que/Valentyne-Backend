<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MusicController extends Controller
{
    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly StorageService   $storage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        $all = Cache::remember('collection:music', 120, function () {
            $list = $this->firestore->getCollection('music', ['orderBy' => 'id', 'direction' => 'desc']);
            return $this->storage->resolveCollection($list, ['song', 'img']);
        });

        $total  = count($all);
        $items  = array_slice($all, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'data'        => array_values($items),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }
}
