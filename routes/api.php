<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,PortalController};
Route::middleware('throttle:login')->group(function(){Route::post('/login',[AuthController::class,'login']);Route::post('/register',[AuthController::class,'register']);});
Route::middleware(['auth:sanctum','active','throttle:api'])->group(function(){
 Route::post('/logout',[AuthController::class,'logout']);Route::get('/snapshot',[PortalController::class,'snapshot']);
 Route::post('/actions/{action}',[PortalController::class,'action']);Route::post('/profile',[PortalController::class,'profile']);
 Route::post('/notifications/{id}/read',[PortalController::class,'readNotice']);
 Route::get('/resources/{id}/download',fn(\Illuminate\Http\Request $r,string $id)=>(new PortalController)->download($r,'resources',$id));
});
