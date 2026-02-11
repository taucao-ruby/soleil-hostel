<?php

use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return 'Web route works!';
});

Route::get('/', function () {
    return response()->json([
        'message' => 'Soleil Hostel API',
        'version' => '1.0',
        'status' => 'running',
    ]);
});
