<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private readonly FirestoreService $firestore) {}

    public function index(): JsonResponse
    {
        $tickets = $this->firestore->getCollection('ticketsList');
        return response()->json($tickets);
    }

    public function types(): JsonResponse
    {
        $types = $this->firestore->getCollection('ticketsTypes');
        return response()->json($types);
    }

    public function draw(): JsonResponse
    {
        [$tickets, $types] = [
            $this->firestore->getCollection('ticketsList'),
            $this->firestore->getCollection('ticketsTypes'),
        ];

        if (empty($tickets) || empty($types)) {
            return response()->json(['error' => 'Sem tickets disponíveis'], 404);
        }

        $selected = $this->weightedPick($types);
        $pool     = array_values(array_filter($tickets, fn($t) => $t['tipo'] === $selected['nome']));
        $source   = count($pool) > 0 ? $pool : $tickets;
        $drawn    = $source[array_rand($source)];
        $ticket   = array_merge($drawn, ['color' => $selected['color']]);

        $nextId = $this->firestore->getMaxId('tickets') + 1;
        $docId  = (string) $nextId;

        $this->firestore->setDocument('tickets', $docId, [
            'id'     => $nextId,
            'text'   => $ticket['text']  ?? '',
            'tipo'   => $ticket['tipo']  ?? '',
            'color'  => $ticket['color'] ?? '',
            'date'   => new \DateTime(),
            'status' => 'valido',
        ]);

        return response()->json(array_merge($ticket, ['docId' => $docId]));
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $status = $request->input('status');
        if (!in_array($status, ['valido', 'trocado', 'usado'])) {
            return response()->json(['error' => 'Status inválido'], 400);
        }

        $this->firestore->updateDocument('tickets', $id, ['status' => $status]);
        return response()->json(['success' => true]);
    }

    private function weightedPick(array $types): array
    {
        $total = array_sum(array_column($types, 'chance'));
        $roll  = (float) rand() / getrandmax() * $total;

        $accumulated = 0;
        foreach ($types as $type) {
            $accumulated += $type['chance'];
            if ($roll <= $accumulated) {
                return $type;
            }
        }

        return $types[array_key_last($types)];
    }
}
