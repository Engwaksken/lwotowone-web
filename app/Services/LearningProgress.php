<?php
namespace App\Services;
class LearningProgress {
 /**
  * Lesson progress for each enrolment row (needs user_id, course_id, preferred_language),
  * counted against the published lessons in the learner's own language.
  */
 public static function forEnrolments($enrolments): array {
  $enrolments=collect($enrolments);if($enrolments->isEmpty())return [];
  $done=\Illuminate\Support\Facades\DB::table('lesson_progress')->whereIn('user_id',$enrolments->pluck('user_id')->unique())->get(['user_id','lesson_id','completed_at'])->groupBy('user_id');
  $passed=\Illuminate\Support\Facades\DB::table('submissions')->whereIn('user_id',$enrolments->pluck('user_id')->unique())->where('status','passed')->get(['user_id','assignment_id'])->groupBy('user_id');
  $assignments=\Illuminate\Support\Facades\DB::table('assignments')->whereIn('course_id',$enrolments->pluck('course_id')->unique())->where('status','published')->get(['id','course_id','content_language'])->groupBy('course_id');
  $lessonCache=[];$out=[];
  foreach($enrolments as $row){
   $language=$row->preferred_language==='lg'?'lg':'en';
   $lessons=$lessonCache[$row->course_id.$language]??=LearningAccess::lessons((int)$row->course_id,$language);
   $completed=collect($done->get($row->user_id,[]))->keyBy('lesson_id');
   $items=$lessons->map(fn($lesson)=>['title'=>($language==='lg'?($lesson->title_lg?:$lesson->title):($lesson->title?:$lesson->title_lg))?:'Lesson '.$lesson->id,'module'=>$language==='lg'?($lesson->module_title_lg?:$lesson->module_title):$lesson->module_title,'completed_at'=>$completed->get($lesson->id)?->completed_at])->values();
   $count=$items->whereNotNull('completed_at')->count();
   $courseAssignments=collect($assignments->get($row->course_id,[]))->where('content_language',$language)->pluck('id');
   $out[$row->user_id.'-'.$row->course_id]=['language'=>$language,'lessons_total'=>$items->count(),'lessons_completed'=>$count,'percent'=>$items->count()?(int)floor(100*$count/$items->count()):0,'last_completed_at'=>$items->max('completed_at'),'awaiting_assessments'=>$courseAssignments->isEmpty()&&collect($assignments->get($row->course_id,[]))->isNotEmpty(),'assignments_total'=>$courseAssignments->count(),'assignments_passed'=>collect($passed->get($row->user_id,[]))->whereIn('assignment_id',$courseAssignments)->pluck('assignment_id')->unique()->count(),'lessons'=>$items->all()];
  }
  return $out;
 }
 public static function fromSnapshot(array $data): array {
  $enrolled=collect($data['enrolments'])->pluck('course_id');
   return collect($data['courses'])->whereIn('id',$enrolled)->map(function($course)use($data){
    $lessons=collect($data['lessons'])->where('course_id',$course->id)->sortBy('sort_order');
   $completed=collect($data['lesson_progress'])->pluck('lesson_id')->unique();
   $done=$lessons->whereIn('id',$completed)->count();
   $assignments=collect($data['assignments'])->where('course_id',$course->id);
   $passed=collect($data['submissions'])->where('status','passed')->pluck('assignment_id')->unique();
   $assessed=$assignments->whereIn('id',$passed)->count();
    $awaiting=LearningAccess::awaitingAssessments((int)$course->id,($data['user']['preferred_language']??'en')==='lg'?'lg':'en');
    $complete=$lessons->count()>0&&$done===$lessons->count()&&!$awaiting&&$assessed===$assignments->count();
    return ['course_id'=>$course->id,'lessons_completed'=>$done,'lessons_total'=>$lessons->count(),'lesson_percent'=>$lessons->count()?(int)floor(100*$done/$lessons->count()):0,'assignments_passed'=>$assessed,'assignments_total'=>$assignments->count(),'awaiting_assessments'=>$awaiting,'completion_ready'=>$complete,'certificate_ready'=>$complete&&\Illuminate\Support\Facades\DB::table('course_certificates')->where('course_id',$course->id)->where('user_id',$data['user']['id'])->exists(),'next_lesson_id'=>$lessons->whereNotIn('id',$completed)->first()?->id];
  })->values()->all();
 }
}
