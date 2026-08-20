<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BookController extends Controller
{
    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly StorageService   $storage,
    ) {}

    /**
     * Sem paginação de propósito: o app baixa todas as fotos antes de mostrar a
     * estante, então página parcial não serviria pra nada. Se a biblioteca
     * passar de umas dezenas de livros, aí sim vale paginar.
     */
    public function index(): JsonResponse
    {
        $books = Cache::remember('collection:books', 120, function () {
            $list = $this->firestore->getCollection('books', [
                'orderBy'   => 'id',
                'direction' => 'asc',
            ]);

            // spine e cover guardam o path do Storage; aqui viram download URL.
            return $this->storage->resolveCollection($list, ['spine', 'cover']);
        });

        // Livro inativo some da estante sem precisar apagar o documento.
        $books = array_values(array_filter($books, fn ($b) => $b['active'] ?? true));

        return response()->json($books);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'   => 'required|string|max:120',
            'author'  => 'required|string|max:60',
            'pages'   => 'required|array|min:1',
            'pages.*' => 'string',
            'color'   => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'spine'   => 'nullable|string',
            'cover'   => 'nullable|string',
            'height'  => 'nullable|integer|min:80|max:400',
            'width'   => 'nullable|integer|min:20|max:200',
            'faceOut' => 'nullable|boolean',
            'active'  => 'nullable|boolean',
        ]);

        $data = array_merge([
            'spine'   => '',
            'cover'   => '',
            'height'  => 190,
            'width'   => 48,
            'faceOut' => false,
            'active'  => true,
        ], $validated, [
            'id'        => $this->firestore->getMaxId('books') + 1,
            'createdAt' => now()->toIso8601String(),
        ]);

        $docId = $this->firestore->createDocument('books', $data);
        Cache::forget('collection:books');

        return response()->json(array_merge($data, ['docId' => $docId]), 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->firestore->deleteDocument('books', $id);
        Cache::forget('collection:books');

        return response()->json(['ok' => true]);
    }
}
