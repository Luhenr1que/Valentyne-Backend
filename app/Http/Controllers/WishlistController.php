<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Paginates;
use App\Services\FirestoreService;
use App\Services\LinkPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly FirestoreService   $firestore,
        private readonly LinkPreviewService $preview,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $all = $this->firestore->getCollection('wishlist', [
            'orderBy'   => 'createdAt',
            'direction' => 'desc',
        ]);

        return response()->json($this->paginate($all, $request));
    }

    public function store(Request $request): JsonResponse
    {
        $url = trim((string) $request->input('url', ''));

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Link inválido'], 422);
        }

        $preview = $this->preview->fetch($url);

        $data = array_merge($preview, [
            'id'        => $this->firestore->getMaxId('wishlist') + 1,
            'createdAt' => now()->toIso8601String(),
        ]);

        $docId = $this->firestore->createDocument('wishlist', $data);

        try {
            $authorizationHeader = 'Bearer 5520|kDgw0F6NLd5hFlkckg4T0ZqDsc8Q91sz15nDRzpT76765c7f';

            $cardPayload = [
                'title'            => $preview['title'] ?: 'Produto sem título',
                'description'      => '',
                'user_id'          => 299,
                'pipeline_id'      => 2895,
                'environment_id'   => 584,
                'parent_card_id'   => null,
                'checklist'        => 0,
                'top'              => true,
                'due_date'         => null,
                'date'             => null,
                'date_conclusion'  => null,
                'refresh_pipeline' => true,
                'list_page'        => 1,
                'list_per_page'    => 20,
                'list_order'       => 'asc',
                'list_sort_by'     => 'sort',
                'search'           => '',
                'filters'          => (object)[],
            ];

            $cardResponse = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $authorizationHeader,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])->post('https://gjb2ytrm-p.cagefast.com/api/card', $cardPayload);

            if ($cardResponse->successful()) {
                $cardUuid = $cardData['card']['uuid'] ?? ($cardData['uuid'] ?? ($cardData['data']['uuid'] ?? null));
                $cardId   = $cardData['card']['id'] ?? ($cardData['id'] ?? ($cardData['data']['id'] ?? null));

                if ($cardUuid && $cardId) {
                    if (!empty($preview['image'])) {
                        $imageResponse = \Illuminate\Support\Facades\Http::get($preview['image']);
                        if ($imageResponse->successful()) {
                            $imageContents = $imageResponse->body();
                            $filename = basename(parse_url($preview['image'], PHP_URL_PATH)) ?: 'product_image.jpg';
                            if (!str_contains($filename, '.')) {
                                $filename .= '.jpg';
                            }

                            \Illuminate\Support\Facades\Http::withHeaders([
                                'Authorization' => $authorizationHeader,
                                'Accept'        => 'application/json',
                            ])->attach('file', $imageContents, $filename)
                              ->post("https://gjb2ytrm-p.cagefast.com/api/cards/{$cardUuid}/annexes");
                        }
                    }

                    \Illuminate\Support\Facades\Http::withHeaders([
                        'Authorization' => $authorizationHeader,
                        'Accept'        => 'application/json',
                        'Content-Type'  => 'application/json',
                    ])->put('https://gjb2ytrm-p.cagefast.com/api/inputs/add-content', [
                        'content'                 => $url,
                        'option_select_id'        => null,
                        'input_card_id'           => '1310',
                        'card_id'                 => (string) $cardId,
                        'content_id'              => 0,
                        'relation_card_id'        => null,
                        'relation_environment_id' => null,
                    ]);
                }
            }
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::error('Erro ao integrar com Cage API: ' . $th->getMessage());
        }

        return response()->json(array_merge($data, ['docId' => $docId]), 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->firestore->deleteDocument('wishlist', $id);

        return response()->json(['ok' => true]);
    }
}
