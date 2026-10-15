<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Hash,Password};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
 public function form(){return view('auth.login');}
 public function registerForm(){$policies=\Illuminate\Support\Facades\DB::table('pages')->whereIn('slug',['terms','privacy'])->where('status','published')->get()->keyBy('slug');return view('auth.register',compact('policies'));}
 public function register(Request $r){
     $phone=$r->is('api/*')?'nullable':'required';
   $d=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:190|unique:users','password'=>'required|string|min:12|max:128|confirmed','phone'=>$phone.'|string|max:40','district'=>'nullable|string|max:100','consent'=>'accepted']+($r->is('api/*')?[]:['terms_read'=>'accepted','privacy_read'=>'accepted']),['terms_read.accepted'=>'Open and read the terms of use before creating your account.','privacy_read.accepted'=>'Open and read the privacy notice before creating your account.']);unset($d['consent'],$d['password_confirmation'],$d['terms_read'],$d['privacy_read']);
   $u=User::create($d+['role'=>'participant','status'=>'active','requires_email_verification'=>true]);$u->forceFill(['terms_accepted_at'=>now()])->save();
   $u->notify(new \App\Notifications\AccountSetup());
   if($r->is('api/*'))return response()->json(['message'=>'Verify your email address before signing in.'],201);
   return redirect('/login')->with('success','Account created. Check your email to verify your address before signing in.');
 }
 public function login(Request $r){
  $d=$r->validate(['email'=>'required|email','password'=>'required|string']);$u=User::where('email',$d['email'])->first();
   if(!$u||!Hash::check($d['password'],$u->password)||$u->status!=='active')throw ValidationException::withMessages(['email'=>'The credentials are incorrect or your account is inactive.']);
   if($u->requires_email_verification&&!$u->email_verified_at)throw ValidationException::withMessages(['email'=>'Please verify your email using the link we sent before signing in.']);
   if($r->is('api/*')){abort_unless($u->role==='participant',403,'The mobile app is for participants.');if($u->must_change_password)throw ValidationException::withMessages(['email'=>'Sign in on the website and change your temporary password before using the app.']);return ['user'=>$u,'token'=>$u->createToken('mobile',['*'],now()->addDays(30))->plainTextToken];}
   Auth::login($u,$r->boolean('remember'));$r->session()->regenerate();return $u->must_change_password?redirect('/change-password'):redirect()->intended('/dashboard');
 }
  public function verifyEmail(Request $r,string $id,string $hash){$u=User::findOrFail($id);abort_unless(hash_equals(sha1($u->email),$hash),403);if(!$u->email_verified_at)$u->forceFill(['email_verified_at'=>now(),'requires_email_verification'=>false])->save();return redirect('/login')->with('success','Email verified. You can now sign in.');}
  public function changePasswordForm(Request $r){return view('auth.change-password');}
  public function changePassword(Request $r){$data=$r->validate(['password'=>'required|string|min:12|max:128|confirmed']);$r->user()->forceFill(['password'=>Hash::make($data['password']),'must_change_password'=>false])->save();$r->user()->tokens()->delete();return redirect('/dashboard')->with('success','Your password has been changed.');}
  public function logout(Request $r){if($r->is('api/*')){$r->user()->currentAccessToken()?->delete();return ['ok'=>true];}Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/');}
 public function forgot(){return view('auth.forgot');}
 public function sendReset(Request $r){$r->validate(['email'=>'required|email']);Password::sendResetLink($r->only('email'));return back()->with('success','If this email is registered, a password reset link has been sent.');}
 public function resetForm(Request $r,string $token){return view('auth.reset',['token'=>$token,'email'=>$r->query('email')]);}
  public function reset(Request $r){$d=$r->validate(['token'=>'required','email'=>'required|email','password'=>'required|min:12|max:128|confirmed']);$status=Password::reset($d,function($u,$p){$u->forceFill(['password'=>Hash::make($p),'remember_token'=>Str::random(60),'must_change_password'=>false])->save();$u->tokens()->delete();});if($status!==Password::PASSWORD_RESET)return back()->withErrors(['email'=>__($status)]);return redirect('/login')->with('success','Password reset. Please sign in.');}
}
