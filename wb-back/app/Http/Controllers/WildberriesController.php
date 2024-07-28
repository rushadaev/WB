<?php

namespace App\Http\Controllers;

use App\Traits\UsesWildberries;
use Illuminate\Http\Request;

class WildberriesController extends Controller
{
    use UsesWildberries;

    public function getQuestions(Request $request)
    {
        $isAnswered = $request->input('isAnswered', false);
        $take = $request->input('take', 2);
        $skip = $request->input('skip', 0);
        $order = $request->input('order', 'dateDesc');
        $nmId = $request->input('nmId');
        $dateFrom = $request->input('dateFrom');
        $dateTo = $request->input('dateTo');

        $response = $this->useWildberries()->getQuestions($isAnswered, $take, $skip, $order, $nmId, $dateFrom, $dateTo);

        return response()->json($response, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}