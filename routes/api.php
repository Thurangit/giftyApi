<?php

use App\Http\Controllers\PayController;
use App\Http\Controllers\ReceivePayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/send/money/', [PayController::class, 'sendMoney'])->name('send_money');
Route::get('/infos/gift/{ref}', [ReceivePayController::class, 'infoGift'])->name('info_gift');

Route::post('/receive/money', [ReceivePayController::class, 'withdraw'])->name('receive_money');


