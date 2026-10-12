<?php

use App\Http\Controllers\Api\ProductHistoryController;
use App\Http\Controllers\Api\StockLevelController;
use App\Http\Controllers\Api\StockMovementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/stock-movements', StockMovementController::class);

Route::get('/stock-levels', StockLevelController::class);

Route::get('/products/{sku}/history', ProductHistoryController::class);
