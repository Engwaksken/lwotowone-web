<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};

return new class extends Migration {
    public function up(): void {
        // MySQL DDL is not transactional: preserve completed steps when retrying a failed migration.
        if(!Schema::hasColumn('courses','prerequisite_course_id'))Schema::table('courses',function(Blueprint $t){$t->foreignId('prerequisite_course_id')->nullable()->constrained('courses')->nullOnDelete();});
        if(!Schema::hasTable('course_modules'))Schema::create('course_modules',function(Blueprint $t){$t->id();$t->foreignId('course_id')->constrained()->restrictOnDelete();$t->string('title');$t->unsignedInteger('position')->default(1);$t->string('status')->default('draft');$t->timestamps();});
        if(!Schema::hasColumn('lessons','module_id'))Schema::table('lessons',function(Blueprint $t){$t->foreignId('module_id')->nullable()->constrained('course_modules')->restrictOnDelete();});
        if(!Schema::hasColumn('resources','lesson_id'))Schema::table('resources',function(Blueprint $t){$t->foreignId('lesson_id')->nullable()->constrained('lessons')->restrictOnDelete();});
        if(!Schema::hasColumn('enrolments','trial_started_at'))Schema::table('enrolments',fn(Blueprint $t)=>$t->timestamp('trial_started_at')->nullable());
        if(!Schema::hasColumn('enrolments','trial_expires_at'))Schema::table('enrolments',fn(Blueprint $t)=>$t->timestamp('trial_expires_at')->nullable()->index());
        if(!Schema::hasColumn('enrolments','payment_confirmed_at'))Schema::table('enrolments',fn(Blueprint $t)=>$t->timestamp('payment_confirmed_at')->nullable());
        DB::table('enrolments')->whereNull('trial_started_at')->whereNull('payment_confirmed_at')->whereIn('user_id',DB::table('users')->where('learning_access_paid',true)->select('id'))->update(['payment_confirmed_at'=>DB::raw('created_at')]);
        if(!Schema::hasTable('application_calls'))Schema::create('application_calls',function(Blueprint $t){$t->id();$t->uuid('token')->unique();$t->string('title');$t->text('description');$t->foreignId('course_id')->nullable()->constrained()->restrictOnDelete();$t->foreignId('opportunity_id')->nullable()->constrained()->restrictOnDelete();$t->dateTime('opens_at');$t->dateTime('closes_at');$t->string('status')->default('draft');$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->timestamps();});
        if(!Schema::hasTable('call_applications'))Schema::create('call_applications',function(Blueprint $t){$t->id();$t->foreignId('call_id')->constrained('application_calls')->restrictOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->text('motivation');$t->string('status')->default('submitted');$t->text('feedback')->nullable();$t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('reviewed_at')->nullable();$t->timestamps();$t->unique(['call_id','user_id']);});
        if(!Schema::hasTable('certificate_templates'))Schema::create('certificate_templates',function(Blueprint $t){$t->id();$t->foreignId('course_id')->unique()->constrained()->restrictOnDelete();$t->string('file_path');$t->string('format');$t->decimal('width_mm',8,2);$t->decimal('height_mm',8,2);$t->json('placements');$t->timestamps();});
        if(!Schema::hasTable('course_certificates'))Schema::create('course_certificates',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('course_id')->constrained()->restrictOnDelete();$t->foreignId('recommended_by')->constrained('users')->restrictOnDelete();$t->dateTime('recommended_at');$t->string('reference')->unique();$t->string('file_path')->nullable();$t->timestamps();$t->unique(['user_id','course_id']);});
    }
    public function down(): void {
        Schema::dropIfExists('course_certificates');Schema::dropIfExists('certificate_templates');Schema::dropIfExists('call_applications');Schema::dropIfExists('application_calls');
        Schema::table('enrolments',fn(Blueprint $t)=>$t->dropColumn(['trial_started_at','trial_expires_at','payment_confirmed_at']));
        Schema::table('resources',fn(Blueprint $t)=>$t->dropConstrainedForeignId('lesson_id'));
        Schema::table('lessons',fn(Blueprint $t)=>$t->dropConstrainedForeignId('module_id'));Schema::dropIfExists('course_modules');
        Schema::table('courses',fn(Blueprint $t)=>$t->dropConstrainedForeignId('prerequisite_course_id'));
    }
};
