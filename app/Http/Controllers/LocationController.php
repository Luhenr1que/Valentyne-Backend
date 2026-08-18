<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(private readonly FirestoreService $firestore) {}

    public function index(): JsonResponse
    {
        return response()->json($this->firestore->getCollection('locations'));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->input('user');
        $lat  = $request->input('lat');
        $lng  = $request->input('lng');

        if (!in_array($user, ['Lu', 'Le'], true) || !is_numeric($lat) || !is_numeric($lng)) {
            return response()->json(['error' => 'user (Lu/Le), lat e lng são obrigatórios'], 422);
        }

        $this->firestore->setDocument('locations', $user, [
            'lat'       => (float) $lat,
            'lng'       => (float) $lng,
            'updatedAt' => new \DateTime(),
        ]);

        return response()->json(['ok' => true]);
    }
}
