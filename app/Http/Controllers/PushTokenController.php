<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function __construct(private readonly FirestoreService $firestore) {}

    public function store(Request $request): JsonResponse
    {
        $token = $request->input('token');
        $user  = $request->input('user');

        if (!$token || !str_starts_with($token, 'ExponentPushToken[')) {
            return response()->json(['error' => 'Token inválido'], 422);
        }

        $existing = $this->firestore->getCollection('push_tokens', [
            'where' => [['token', '==', $token]],
        ]);

        if (empty($existing)) {
            $this->firestore->createDocument('push_tokens', ['token' => $token, 'user' => $user]);

            return response()->json(['ok' => true]);
        }

        // Mesmo aparelho pode trocar de dono, e os tokens antigos nao tem dono
        // nenhum. Sem $user nao sobrescreve — senao um caller velho apaga o dono.
        if ($user) {
            foreach ($existing as $doc) {
                if (($doc['user'] ?? null) !== $user) {
                    $this->firestore->updateDocument('push_tokens', $doc['docId'], ['user' => $user]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['error' => 'token obrigatório'], 422);
        }

        $existing = $this->firestore->getCollection('push_tokens', [
            'where' => [['token', '==', $token]],
        ]);

        foreach ($existing as $doc) {
            $this->firestore->deleteDocument('push_tokens', $doc['docId']);
        }

        return response()->json(['ok' => true]);
    }
}
