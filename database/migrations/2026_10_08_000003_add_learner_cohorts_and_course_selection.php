<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('mel_cohorts',function(Blueprint $t){$t->id();$t->string('name',120);$t->string('enrollment_category',100);$t->string('learner_number_prefix',32);$t->string('learner_number_format',100)->default('{prefix}-{year}-{sequence}');$t->unsignedInteger('next_sequence')->default(1);$t->unsignedTinyInteger('sequence_padding')->default(4);$t->boolean('active')->default(true)->index();$t->timestamps();});
  Schema::create('mel_settlements',function(Blueprint $t){$t->id();$t->string('name',160)->unique();$t->boolean('active')->default(true)->index();$t->timestamps();});
  Schema::table('users',function(Blueprint $t){
   $t->dropColumn('venture');
   $t->foreignId('selected_course_id')->nullable()->after('email')->constrained('courses')->restrictOnDelete();
   $t->foreignId('cohort_id')->nullable()->constrained('mel_cohorts')->restrictOnDelete();
  });
 }
 public function down(): void {
  Schema::table('users',function(Blueprint $t){$t->dropConstrainedForeignId('cohort_id');$t->dropConstrainedForeignId('selected_course_id');$t->string('venture')->nullable();});
  Schema::dropIfExists('mel_settlements');Schema::dropIfExists('mel_cohorts');
 }
};
