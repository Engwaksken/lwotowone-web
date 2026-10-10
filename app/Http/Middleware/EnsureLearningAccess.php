<?php
namespace App\Http\Middleware;
use Closure;
class EnsureLearningAccess {
 public function handle($request,Closure $next){$user=$request->user();if(!$user||$user->role!=='participant')return $next($request);if(!$user->profile_complete){if($request->expectsJson())return response()->json(['message'=>'Complete your learner profile first.','redirect'=>'/profile'],403);return redirect('/profile')->with('learning_access_notice','Complete your learner profile to continue.');}if($user->learningAccessOpen())return $next($request);$message=$user->full_access_until!==null?'Your 24-hour full access has ended. Make payment to continue learning.':'Payment is required to access learning pages.';if($request->expectsJson())return response()->json(['message'=>$message,'redirect'=>'/portal/payment'],403);return redirect('/portal/payment')->with('learning_access_notice',$message);}
}
