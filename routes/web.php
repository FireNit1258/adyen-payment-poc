<?php

use App\Http\Controllers\AdyenController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('/checkout', [PaymentController::class, 'checkoutPage'])->name('payment.checkout');
Route::post('/process-payment', [PaymentController::class, 'processPayment'])->name('payment.process');

Route::get('/payment-handleResponse', [PaymentController::class, 'handleResponse'])->name('payment.handleResponse');

Route::get('/payment/thank-you', function () {
    return view('payment.thankYou');
})->name('payment.thankYou');

Route::get('/payment/failed', function () {
    return view('payment.failed');
})->name('payment.failed');


Route::post('/get-payment-methods', [AdyenController::class, 'getPaymentMethods']);
// Route::post('/process-payment', [AdyenController::class, 'processPayment']);
Route::post('/submit-additional-details', [AdyenController::class, 'submitAdditionalDetails']);
