<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function __construct(private readonly FirestoreService $firestore) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->input('user');

        if (!in_array($user, ['Lu', 'Le'], true)) {
            return response()->json(['error' => 'user deve ser Lu ou Le'], 422);
        }

        $this->firestore->createDocument('logins', [
            'user'     => $user,
            'loggedAt' => new \DateTime(),
        ]);

        return response()->json(['ok' => true]);
    }
}
