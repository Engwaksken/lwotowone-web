<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable {
 use Notifiable, HasApiTokens;
 protected $fillable=['name','email','password','phone','district','role','status','bio','expertise','fcm_token','learner_no','enrollment_date','enrollment_category','gender','location','urban_rural','learner_age','refugee','settlement','pwd','impairment','education_level','learner_status','verified_outcomes','other_verified_outcome','yiw_before','transformation_objective','after_work_status','after_work_pathway','profile_complete','learning_access_paid','selected_course_id','cohort_id'];
 protected $hidden=['password','remember_token'];
 protected function casts(): array {return ['password'=>'hashed','email_verified_at'=>'datetime','refugee'=>'boolean','pwd'=>'boolean','profile_complete'=>'boolean','learning_access_paid'=>'boolean'];}
 public function staff(): bool {return in_array($this->role,['admin','manager','instructor','mentor']);}
 public function manager(): bool {return in_array($this->role,['admin','manager']);}
}
