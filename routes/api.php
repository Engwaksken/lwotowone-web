<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,KnowledgeGameController,PortalController};
Route::post('/stripe/webhook',[PortalController::class,'stripeWebhook'])->middleware('throttle:api');
Route::get('/policies',fn()=>['policies'=>\App\Services\Policies::all()])->middleware('throttle:api');
Route::middleware('throttle:login')->group(function(){Route::post('/login',[AuthController::class,'login']);Route::post('/register',[AuthController::class,'register']);});
Route::middleware(['auth:sanctum','active','throttle:api'])->group(function(){
 Route::post('/logout',[AuthController::class,'logout']);Route::get('/snapshot',[PortalController::class,'snapshot']);
 Route::post('/device-token',[PortalController::class,'registerDeviceToken']);Route::delete('/device-token',[PortalController::class,'removeDeviceToken']);
  Route::middleware(\App\Http\Middleware\EnsureLearningAccess::class)->group(function(){
    Route::post('/actions/{action}',[PortalController::class,'action']);
    Route::get('/resources/{id}/download',fn(\Illuminate\Http\Request $r,string $id)=>(new PortalController)->download($r,'resources',$id));
    Route::get('/lessons/{id}/download',[PortalController::class,'lessonMedia']);
  });
  Route::post('/profile',[PortalController::class,'profile']);
  Route::post('/enrollment-requests',[PortalController::class,'requestEnrollment'])->middleware('throttle:10,1');
  Route::post('/download-requests',[\App\Http\Controllers\DownloadController::class,'store'])->middleware('throttle:20,1');
  Route::get('/downloads/{id}',[\App\Http\Controllers\DownloadController::class,'download'])->whereNumber('id')->middleware('throttle:30,1');
  Route::get('/downloads/{id}/attachments/{attachment}',[\App\Http\Controllers\DownloadController::class,'download'])->whereNumber(['id','attachment'])->middleware('throttle:30,1');
  Route::get('/knowledge-game/topics',[KnowledgeGameController::class,'topics']);
  Route::get('/knowledge-game/history',[KnowledgeGameController::class,'history']);
  Route::post('/knowledge-game/attempts',[KnowledgeGameController::class,'start']);
  Route::post('/knowledge-game/attempts/{attemptId}/submit',[KnowledgeGameController::class,'submit']);
  Route::get('/profile/photo',[PortalController::class,'profilePhoto']);
  Route::post('/profile/photo',[PortalController::class,'uploadProfilePhoto']);
  Route::delete('/profile/photo',[PortalController::class,'deleteProfilePhoto']);
  Route::post('/notifications/{id}/read',[PortalController::class,'readNotice']);

Route::post('/notifications/push',[PortalController::class,'pushNotice']);
Route::post('/sms',[PortalController::class,'sendSms']);

Route::middleware(['auth:sanctum','active','throttle:api'])->group(function(){
    Route::post('/payment-intent',[PortalController::class,'createPaymentIntent']);
    Route::get('/transactions',[PortalController::class,'listTransactions']);
});
});
