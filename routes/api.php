<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
Route::get('/health', fn() => response()->json(['status'=>'ok']));
Route::post('/auth/login',[ApiController::class,'login']);
Route::middleware('operator')->group(function () {
    Route::get('/categories',[ApiController::class,'categories']);
    Route::get('/books',[ApiController::class,'books']);
    Route::get('/books/{id}',[ApiController::class,'book']);
    Route::get('/books/{id}/cover',[ApiController::class,'getCover']);
    Route::get('/members',[ApiController::class,'members']);
    Route::get('/members/{id}',[ApiController::class,'member']);
    Route::post('/members',[ApiController::class,'createMember']);
    Route::get('/loans/overdue',[ApiController::class,'overdue']);
    Route::get('/loans',[ApiController::class,'loans']);
    Route::get('/loans/{id}',[ApiController::class,'loan']);
    Route::post('/loans',[ApiController::class,'lend']);
    Route::post('/loans/{id}/items/{item}/return',[ApiController::class,'returnItem']);
    Route::middleware('operator:admin')->group(function() {
        Route::post('/users',[ApiController::class,'createUser']);
        Route::post('/books',[ApiController::class,'createBook']);
        Route::put('/books/{id}',[ApiController::class,'updateBook']);
        Route::delete('/books/{id}',[ApiController::class,'deleteBook']);
        Route::put('/books/{id}/cover',[ApiController::class,'putCover']);
        Route::delete('/books/{id}/cover',[ApiController::class,'deleteCover']);
        Route::get('/reports/most-borrowed',[ApiController::class,'report']);
    });
});
