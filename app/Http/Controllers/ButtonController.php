<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ButtonController extends Controller
{
    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly StorageService   $storage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $options = [];
        if ($request->boolean('active')) {
            $options['where'] = [['active', '==', true]];
        }

        $list = $this->firestore->getCollection('bottons', $options);
        $list = $this->storage->resolveCollection($list, ['img']);
        return response()->json($list);
    }
}
