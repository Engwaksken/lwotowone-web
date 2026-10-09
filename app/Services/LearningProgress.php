<?php
namespace App\Services;
class LearningProgress {
 public static function fromSnapshot(array $data): array {
  $enrolled=collect($data['enrolments'])->pluck('course_id');
   return collect($data['courses'])->whereIn('id',$enrolled)->map(function($course)use($data){
    $lessons=collect($data['lessons'])->where('course_id',$course->id)->sortBy('sort_order');
   $completed=collect($data['lesson_progress'])->pluck('lesson_id')->unique();
   $done=$lessons->whereIn('id',$completed)->count();
   $assignments=collect($data['assignments'])->where('course_id',$course->id);
   $passed=collect($data['submissions'])->where('status','passed')->pluck('assignment_id')->unique();
   $assessed=$assignments->whereIn('id',$passed)->count();
    $complete=$lessons->count()>0&&$done===$lessons->count()&&$assessed===$assignments->count();
    return ['course_id'=>$course->id,'lessons_completed'=>$done,'lessons_total'=>$lessons->count(),'lesson_percent'=>$lessons->count()?(int)floor(100*$done/$lessons->count()):0,'assignments_passed'=>$assessed,'assignments_total'=>$assignments->count(),'completion_ready'=>$complete,'certificate_ready'=>$complete&&\Illuminate\Support\Facades\DB::table('course_certificates')->where('course_id',$course->id)->where('user_id',$data['user']['id'])->exists(),'next_lesson_id'=>$lessons->whereNotIn('id',$completed)->first()?->id];
  })->values()->all();
 }
}
