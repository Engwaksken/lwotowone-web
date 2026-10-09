<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LearningAccess
{
    public static function enrollment(User $user,int $courseId): ?object {return DB::table('enrolments')->where('user_id',$user->id)->where('course_id',$courseId)->first();}
    public static function allowed(User $user,int $courseId): bool {
        $row=self::enrollment($user,$courseId);
        $prerequisite=DB::table('courses')->where('id',$courseId)->value('prerequisite_course_id');
        return $user->profile_complete&&$row&&(!$prerequisite||self::completionReady($user,(int)$prerequisite))&&($row->payment_confirmed_at||(!$row->trial_started_at&&$user->learning_access_paid)||($row->trial_expires_at&&now()->lt(Carbon::parse($row->trial_expires_at))));
    }
    public static function requireCourse(User $user,int $courseId): void {
        Workflow::enrolled($user,$courseId);
        abort_unless(self::allowed($user,$courseId),403,'Complete the prerequisite course and confirm payment if your 12-hour trial has ended.');
    }
    public static function lessons(int $courseId) {
        return DB::table('lessons')->leftJoin('course_modules','course_modules.id','=','lessons.module_id')->where('lessons.course_id',$courseId)->where('lessons.status','published')
            ->where(fn($q)=>$q->whereNull('lessons.module_id')->orWhere(fn($q)=>$q->where('course_modules.status','published')->where('course_modules.course_id',$courseId)))
            ->select('lessons.*','course_modules.title as module_title')->orderByRaw('COALESCE(course_modules.position, 0)')->orderBy('course_modules.id')->orderBy('lessons.position')->orderBy('lessons.id')->get();
    }
    public static function lessonAllowed(User $user,object $lesson): bool {
        if(!self::allowed($user,(int)$lesson->course_id))return false;
        $done=DB::table('lesson_progress')->where('user_id',$user->id)->pluck('lesson_id');
        foreach(self::lessons((int)$lesson->course_id) as $item){if($item->id===$lesson->id)return true;if(!$done->contains($item->id))return false;}
        return false;
    }
    public static function resourceAllowed(User $user,object $resource): bool {
        if(!self::allowed($user,(int)$resource->course_id))return false;
        if(!$resource->lesson_id)return true;
        $lesson=DB::table('lessons')->where('id',$resource->lesson_id)->where('course_id',$resource->course_id)->where('status','published')->first();
        return $lesson&&self::lessonAllowed($user,$lesson);
    }
    public static function lessonsComplete(User $user,int $courseId): bool {
        $ids=self::lessons($courseId)->pluck('id');
        return $ids->isNotEmpty()&&DB::table('lesson_progress')->where('user_id',$user->id)->whereIn('lesson_id',$ids)->count()===$ids->count();
    }
    public static function completionReady(User $user,int $courseId): bool {
        $ids=DB::table('assignments')->where('course_id',$courseId)->where('status','published')->pluck('id');
        return self::lessonsComplete($user,$courseId)&&DB::table('submissions')->where('user_id',$user->id)->whereIn('assignment_id',$ids)->where('status','passed')->count()===$ids->count();
    }
    public static function trial(int $userId,int $courseId): void {
        DB::table('enrolments')->insertOrIgnore(['user_id'=>$userId,'course_id'=>$courseId,'trial_started_at'=>now(),'trial_expires_at'=>now()->addHours(12),'created_at'=>now(),'updated_at'=>now()]);
    }
}
