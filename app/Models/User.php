<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable {
 use Notifiable, HasApiTokens;
 protected $fillable=['name','email','password','phone','district','role','status','bio','expertise'];
 protected $hidden=['password','remember_token'];
 protected function casts(): array {return ['password'=>'hashed','email_verified_at'=>'datetime'];}
 public function staff(): bool {return in_array($this->role,['admin','manager','instructor','mentor']);}
 public function manager(): bool {return in_array($this->role,['admin','manager']);}
}
