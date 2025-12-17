<?php

namespace App\Http\Controllers\Api\V1\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuggestRequest;
use App\Services\SearchService;

class SearchController extends Controller
{
    /**
     * GET /api/v1/search/suggest?city=Delhi&q=pa&size=10&quick=1
     * - city: required
     * - q: required
     * - size: default 10 (1..20)
     * - quick: optional (1 => eta <= 30 min)
     */
    public function suggest(SuggestRequest $request, SearchService $search)
    {
        $data = $search->suggest(
            city: $request->city(),
            q:    $request->q(),
            size: $request->size(),
            quick:$request->quick()
        );

        return response()->json([
            'ok'   => true,
            'data' => $data,
        ]);
    }
}
