<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use QueryPilot\QueryPilotAgent;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


//Find a user which email is "heidenreich.olen@example.org" and also show there product and profile data
Route::get('/querypilot-test', function () {
    try {
        $start  = microtime(true);
        $agent  = app(QueryPilotAgent::class);

        $response = $agent->prompt(
            request('q',  'Give me first record of user table'),
            provider: config('querypilot.provider')
        );

        return response()->json([
            'success'       => true,
            'answer'        => $response['answer'] ?? '',
            'table'         => $response['table'] ?? '',
            'count'         => $response['count'] ?? '',
            'rows'          => $response['rows'] ?? [],
            'total_time_ms' => round((microtime(true) - $start) * 1000),
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error'   => $e->getMessage(),
        ], 500);
    }
});
