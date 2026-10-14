<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
Route::get('/health', fn() => response()->json(['status'=>'ok']));
