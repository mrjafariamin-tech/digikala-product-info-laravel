<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProductController::class, 'getProductInfo'])->name('get_product_info');
Route::post('/', [ProductController::class, 'getProductInfo']); // handle POST
Route::get('/export', [ProductController::class, 'exportToExcel'])->name('export_to_excel');