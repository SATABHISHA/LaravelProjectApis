<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\NumberController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/hello', function () {
    return response()->json(['message' => 'Hello, API!']);
});

Route::get('/users', [UserController::class, 'index']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/submitaccountsdetails', [AccountController::class, 'submit']);
Route::get('/AccountsDetailsByDate/{user_id}/{date}', [AccountController::class, 'accountsByDate']);
Route::get('/RecentAccounts/{user_id}', [AccountController::class, 'recentAccounts']);
Route::get('/AllAccountsByUser/{user_id}', [AccountController::class, 'allAccountsByUser']);
Route::post('/upload-file', [FileController::class, 'upload']);
Route::post('/number-to-words', [NumberController::class, 'numberToWords']);