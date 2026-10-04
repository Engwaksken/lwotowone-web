<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
class AppServiceProvider extends ServiceProvider {
 public function boot(): void {
  \Illuminate\Support\Facades\View::composer('layout',function($view){$view->with('contentPages',\Illuminate\Support\Facades\DB::table('pages')->where('status','published')->whereNotIn('slug',['privacy','terms'])->get(['title','slug']));});
  \Illuminate\Support\Facades\View::composer('public.home', function($view){$view->with('site',\Illuminate\Support\Facades\DB::table('settings')->pluck('value','key')->all());});
  RateLimiter::for('login', fn(Request $r)=>Limit::perMinute(10)->by(strtolower((string)$r->input('email')).'|'.$r->ip()));
  RateLimiter::for('api', fn(Request $r)=>Limit::perMinute(90)->by($r->user()?->id ?: $r->ip()));
 }
}
