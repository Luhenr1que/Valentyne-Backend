<?php

namespace App\Http\Controllers;

use App\Services\FirestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TermoController extends Controller
{
    public function __construct(
        private readonly FirestoreService $firestore,
    ) {}

    /**
     * Devolve a lista inteira de palavras e a palavra do dia.
     *
     * A lista completa vai junto de proposito: o app precisa dela pra validar
     * se a tentativa e uma palavra existente. Isso significa que a resposta do
     * dia esta no payload — como em qualquer Wordle, que embarca o dicionario
     * no cliente. Nao ha o que esconder num app de duas pessoas.
     *
     * A palavra do dia sai DAQUI, nao do celular: assim os dois jogam a mesma
     * mesmo com fuso ou relogio diferente. Derivada do dia do ano, nao de
     * random — random daria palavra nova a cada request.
     */
    public function index(): JsonResponse
    {
        $palavras = Cache::remember('collection:termo', 600, function () {
            $lista = $this->firestore->getCollection('termo', [
                'pageSize' => 300,
                'orderBy'  => 'palavra',
            ]);

            // Palavra inativa some do jogo sem precisar apagar o documento.
            $lista = array_values(array_filter(
                $lista,
                fn ($p) => ($p['ativa'] ?? true) && strlen($p['palavra'] ?? '') === 5
            ));

            return array_map(fn ($p) => [
                'palavra' => mb_strtoupper($p['palavra']),
                'tipo'    => $p['tipo'] ?? 'aleatoria',
            ], $lista);
        });

        if (empty($palavras)) {
            return response()->json(['palavras' => [], 'doDia' => null]);
        }

        $dia = (int) floor(now()->startOfDay()->timestamp / 86400);

        return response()->json([
            'palavras' => $palavras,
            'doDia'    => $palavras[$dia % count($palavras)],
        ]);
    }
}
