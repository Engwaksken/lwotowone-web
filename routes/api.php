<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,PortalController};
Route::post('/stripe/webhook',[PortalController::class,'stripeWebhook'])->middleware('throttle:api');
Route::middleware('throttle:login')->group(function(){Route::post('/login',[AuthController::class,'login']);Route::post('/register',[AuthController::class,'register']);});
Route::middleware(['auth:sanctum','active','throttle:api'])->group(function(){
 Route::post('/logout',[AuthController::class,'logout']);Route::get('/snapshot',[PortalController::class,'snapshot']);
 Route::post('/device-token',[PortalController::class,'registerDeviceToken']);Route::delete('/device-token',[PortalController::class,'removeDeviceToken']);
  Route::middleware(\App\Http\Middleware\EnsureLearningAccess::class)->group(function(){
    Route::post('/actions/{action}',[PortalController::class,'action']);
    Route::get('/resources/{id}/download',fn(\Illuminate\Http\Request $r,string $id)=>(new PortalController)->download($r,'resources',$id));
  });
  Route::post('/profile',[PortalController::class,'profile']);
  Route::post('/notifications/{id}/read',[PortalController::class,'readNotice']);

Route::post('/notifications/push',[PortalController::class,'pushNotice']);
Route::post('/sms',[PortalController::class,'sendSms']);

Route::middleware(['auth:sanctum','active','throttle:api'])->group(function(){
    Route::post('/payment-intent',[PortalController::class,'createPaymentIntent']);
    Route::get('/transactions',[PortalController::class,'listTransactions']);
});
});
