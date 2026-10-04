<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
 Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->timestamp('email_verified_at')->nullable();$t->string('password');$t->string('role')->default('participant')->index();$t->string('status')->default('active');$t->string('phone')->nullable();$t->string('district')->nullable();$t->text('bio')->nullable();$t->string('expertise')->nullable();$t->rememberToken();$t->timestamps();});
 Schema::create('password_reset_tokens',function(Blueprint $t){$t->string('email')->primary();$t->string('token');$t->timestamp('created_at')->nullable();});
 Schema::create('personal_access_tokens',function(Blueprint $t){$t->id();$t->morphs('tokenable');$t->text('name');$t->string('token',64)->unique();$t->text('abilities')->nullable();$t->timestamp('last_used_at')->nullable();$t->timestamp('expires_at')->nullable()->index();$t->timestamps();});
 Schema::create('pages',function(Blueprint $t){$t->id();$t->string('title',255);$t->string('slug',255)->unique();$t->text('body');$t->string('status')->default('draft');$t->string('meta_description',255)->nullable();$t->timestamps();});
 Schema::create('programs',function(Blueprint $t){$t->id();$t->string('title',255);$t->string('slug',255)->unique();$t->string('category')->default('Agribusiness');$t->text('summary');$t->text('body');$t->string('status')->default('draft');$t->timestamps();});
 Schema::create('courses',function(Blueprint $t){$t->id();$t->string('title',255);$t->foreignId('program_id')->constrained('programs')->restrictOnDelete();$t->foreignId('instructor_id')->constrained('users')->restrictOnDelete();$t->text('summary');$t->string('level')->default('Beginner');$t->unsignedInteger('duration_hours');$t->string('status')->default('draft');$t->timestamps();});
 Schema::create('lessons',function(Blueprint $t){$t->id();$t->foreignId('course_id')->constrained('courses')->restrictOnDelete();$t->string('title',255);$t->text('body');$t->string('video_url',1000)->nullable();$t->unsignedInteger('position');$t->string('status')->default('draft');$t->timestamps();});
 Schema::create('assignments',function(Blueprint $t){$t->id();$t->foreignId('course_id')->constrained('courses')->restrictOnDelete();$t->string('title',255);$t->text('instructions');$t->dateTime('due_at')->nullable();$t->unsignedInteger('pass_mark');$t->string('status')->default('draft');$t->timestamps();});
 Schema::create('resources',function(Blueprint $t){$t->id();$t->foreignId('course_id')->constrained('courses')->restrictOnDelete();$t->string('title',255);$t->text('description');$t->string('file_path')->nullable();$t->string('status')->default('draft');$t->timestamps();});
 Schema::create('skills',function(Blueprint $t){$t->id();$t->string('title',255);$t->string('category',255);$t->text('description');$t->string('status')->default('draft');$t->timestamps();});
 Schema::create('slots',function(Blueprint $t){$t->id();$t->foreignId('mentor_id')->constrained('users')->restrictOnDelete();$t->string('title',255);$t->dateTime('starts_at');$t->dateTime('ends_at');$t->string('mode')->default('In person');$t->string('location',255);$t->string('status')->default('open');$t->timestamps();});
 Schema::create('opportunities',function(Blueprint $t){$t->id();$t->string('title',255);$t->string('type')->default('Employment');$t->string('organisation',255);$t->string('location',255);$t->text('description');$t->dateTime('deadline');$t->string('status')->default('draft');$t->timestamps();});
 Schema::create('events',function(Blueprint $t){$t->id();$t->string('title',255);$t->text('description');$t->dateTime('starts_at');$t->dateTime('ends_at');$t->string('location',255);$t->unsignedInteger('capacity');$t->string('status')->default('draft');$t->timestamps();});
 Schema::create('posts',function(Blueprint $t){$t->id();$t->string('title',255);$t->string('slug',255)->unique();$t->text('body');$t->string('status')->default('draft');$t->timestamps();});
 Schema::create('announcements',function(Blueprint $t){$t->id();$t->string('title',255);$t->text('body');$t->string('status')->default('draft');$t->timestamps();});
 Schema::create('settings',function(Blueprint $t){$t->id();$t->string('key',255)->unique();$t->text('value');$t->timestamps();});

 Schema::create('enrolments',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('course_id')->constrained()->restrictOnDelete();$t->timestamps();$t->unique(['user_id','course_id']);});
 Schema::create('lesson_progress',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('lesson_id')->constrained()->cascadeOnDelete();$t->timestamp('completed_at');$t->timestamps();$t->unique(['user_id','lesson_id']);});
 Schema::create('submissions',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('assignment_id')->constrained()->restrictOnDelete();$t->text('body');$t->string('file_path')->nullable();$t->string('status')->default('submitted');$t->unsignedInteger('score')->nullable();$t->text('feedback')->nullable();$t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();$t->unique(['user_id','assignment_id']);});
 Schema::create('practice_logs',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('skill_id')->constrained()->restrictOnDelete();$t->string('title');$t->text('body');$t->unsignedInteger('minutes');$t->date('practised_on');$t->string('status')->default('pending');$t->text('feedback')->nullable();$t->timestamps();});
 Schema::create('bookings',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('slot_id')->unique()->constrained()->restrictOnDelete();$t->text('goal');$t->string('status')->default('requested');$t->text('notes')->nullable();$t->timestamp('reminded_at')->nullable();$t->timestamps();});
 Schema::create('applications',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('opportunity_id')->constrained()->restrictOnDelete();$t->text('motivation');$t->string('status')->default('submitted');$t->text('feedback')->nullable();$t->timestamps();$t->unique(['user_id','opportunity_id']);});
 Schema::create('enterprises',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('title');$t->string('sector');$t->text('idea');$t->text('business_plan')->nullable();$t->string('stage')->default('idea');$t->timestamps();});
 Schema::create('transactions',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('enterprise_id')->constrained()->restrictOnDelete();$t->string('type');$t->decimal('amount',14,2);$t->string('description');$t->date('occurred_on');$t->timestamps();});
 Schema::create('event_registrations',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('event_id')->constrained()->restrictOnDelete();$t->string('status')->default('registered');$t->timestamps();$t->unique(['user_id','event_id']);});
 Schema::create('contacts',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->text('message');$t->string('status')->default('new');$t->timestamps();});
 Schema::create('notifications',function(Blueprint $t){$t->uuid('id')->primary();$t->string('type');$t->morphs('notifiable');$t->text('data');$t->timestamp('read_at')->nullable();$t->timestamps();});
 Schema::create('audit_logs',function(Blueprint $t){$t->id();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->string('action');$t->string('module');$t->unsignedBigInteger('record_id')->nullable();$t->timestamps();});
 Schema::create('sync_actions',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->uuid('client_id');$t->string('action');$t->text('response');$t->timestamps();$t->unique(['user_id','client_id']);});
 Schema::create('jobs',function(Blueprint $t){$t->id();$t->string('queue')->index();$t->longText('payload');$t->unsignedTinyInteger('attempts');$t->unsignedInteger('reserved_at')->nullable();$t->unsignedInteger('available_at');$t->unsignedInteger('created_at');});
 Schema::create('failed_jobs',function(Blueprint $t){$t->id();$t->string('uuid')->unique();$t->text('connection');$t->text('queue');$t->longText('payload');$t->longText('exception');$t->timestamp('failed_at')->useCurrent();});
 }
 public function down(): void {
Schema::dropIfExists('failed_jobs');
Schema::dropIfExists('jobs');
Schema::dropIfExists('sync_actions');
Schema::dropIfExists('audit_logs');
Schema::dropIfExists('notifications');
Schema::dropIfExists('contacts');
Schema::dropIfExists('event_registrations');
Schema::dropIfExists('transactions');
Schema::dropIfExists('enterprises');
Schema::dropIfExists('applications');
Schema::dropIfExists('bookings');
Schema::dropIfExists('practice_logs');
Schema::dropIfExists('submissions');
Schema::dropIfExists('lesson_progress');
Schema::dropIfExists('enrolments');
Schema::dropIfExists('settings');
Schema::dropIfExists('announcements');
Schema::dropIfExists('posts');
Schema::dropIfExists('events');
Schema::dropIfExists('opportunities');
Schema::dropIfExists('slots');
Schema::dropIfExists('skills');
Schema::dropIfExists('resources');
Schema::dropIfExists('assignments');
Schema::dropIfExists('lessons');
Schema::dropIfExists('courses');
Schema::dropIfExists('programs');
Schema::dropIfExists('pages');
Schema::dropIfExists('personal_access_tokens');
Schema::dropIfExists('password_reset_tokens');
Schema::dropIfExists('users');
 }
};
