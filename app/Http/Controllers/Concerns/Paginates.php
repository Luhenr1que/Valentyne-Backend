<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait Paginates
{
    protected function paginate(array $all, Request $request): array
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $total   = count($all);
        $items   = array_slice($all, ($page - 1) * $perPage, $perPage);

        return [
            'data'        => array_values($items),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }
}
