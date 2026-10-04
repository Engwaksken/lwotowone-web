<?php
use Illuminate\Support\Facades\{Artisan,DB,Schedule};
use App\Services\Workflow;
Artisan::command('mentorship:remind',function(){
 DB::table('bookings')->where('status','confirmed')->whereNull('reminded_at')->orderBy('id')->chunkById(100,function($rows){foreach($rows as $b){$s=DB::table('slots')->find($b->slot_id);if($s&&now()->lt($s->starts_at)&&now()->addMinutes(30)->gte($s->starts_at)){Workflow::notify($b->user_id,'Mentorship starts soon',$s->title.' at '.$s->starts_at);Workflow::notify($s->mentor_id,'Mentorship starts soon',$s->title.' at '.$s->starts_at);DB::table('bookings')->where('id',$b->id)->update(['reminded_at'=>now()]);}}});
})->purpose('Send in-app and email reminders 30 minutes before confirmed sessions');
Schedule::command('mentorship:remind')->everyMinute()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
