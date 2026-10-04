<?php
namespace App\Http\Middleware;
use Closure;
class ActiveAccount {
 public function handle($request,Closure $next){abort_unless($request->user()?->status==='active',403,'Your account is inactive. Please contact support.');return $next($request);}
}
