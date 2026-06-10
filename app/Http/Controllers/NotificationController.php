<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function send(Request $request): JsonResponse
    {
        $title = $request->input('title', 'Valentyne 💌');
        $body  = $request->input('body', '');
        $data  = $request->input('data', []);

        if (!$body) {
            return response()->json(['error' => 'body obrigatório'], 422);
        }

        $this->notifications->send($title, $body, $data);

        return response()->json(['ok' => true]);
    }
}
