<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Hash,Password};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
 public function form(){return view('auth.login');}
 public function registerForm(){return view('auth.register');}
 public function register(Request $r){
     $phone=$r->is('api/*')?'nullable':'required';
   $d=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:190|unique:users','password'=>'required|string|min:12|max:128|confirmed','phone'=>$phone.'|string|max:40','district'=>'nullable|string|max:100','consent'=>'accepted']);unset($d['consent'],$d['password_confirmation']);
  $u=User::create($d+['role'=>'participant','status'=>'active']);
  if($r->is('api/*'))return response()->json(['user'=>$u,'token'=>$u->createToken('mobile',['*'],now()->addDays(30))->plainTextToken],201);
  Auth::login($u);$r->session()->regenerate();return redirect('/dashboard');
 }
 public function login(Request $r){
  $d=$r->validate(['email'=>'required|email','password'=>'required|string']);$u=User::where('email',$d['email'])->first();
  if(!$u||!Hash::check($d['password'],$u->password)||$u->status!=='active')throw ValidationException::withMessages(['email'=>'The credentials are incorrect or your account is inactive.']);
  if($r->is('api/*')){abort_unless($u->role==='participant',403,'The mobile app is for participants.');return ['user'=>$u,'token'=>$u->createToken('mobile',['*'],now()->addDays(30))->plainTextToken];}
  Auth::login($u,$r->boolean('remember'));$r->session()->regenerate();return redirect()->intended('/dashboard');
 }
 public function logout(Request $r){if($r->is('api/*')){$r->user()->currentAccessToken()?->delete();return ['ok'=>true];}Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/');}
 public function forgot(){return view('auth.forgot');}
 public function sendReset(Request $r){$r->validate(['email'=>'required|email']);Password::sendResetLink($r->only('email'));return back()->with('success','If this email is registered, a password reset link has been sent.');}
 public function resetForm(Request $r,string $token){return view('auth.reset',['token'=>$token,'email'=>$r->query('email')]);}
 public function reset(Request $r){$d=$r->validate(['token'=>'required','email'=>'required|email','password'=>'required|min:12|max:128|confirmed']);$status=Password::reset($d,function($u,$p){$u->forceFill(['password'=>Hash::make($p),'remember_token'=>Str::random(60)])->save();$u->tokens()->delete();});if($status!==Password::PASSWORD_RESET)return back()->withErrors(['email'=>__($status)]);return redirect('/login')->with('success','Password reset. Please sign in.');}
}
