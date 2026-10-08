<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::table('users',function(Blueprint $t){
   $t->string('learner_no')->nullable()->unique(); $t->string('venture')->nullable(); $t->date('enrollment_date')->nullable();
   $t->string('enrollment_category')->nullable(); $t->string('gender')->nullable(); $t->string('location')->nullable();
   $t->string('urban_rural')->nullable(); $t->unsignedSmallInteger('learner_age')->nullable(); $t->boolean('refugee')->default(false);
   $t->string('settlement')->nullable(); $t->boolean('pwd')->default(false); $t->string('impairment')->nullable();
   $t->string('education_level')->nullable(); $t->string('learner_status')->nullable(); $t->text('verified_outcomes')->nullable();
   $t->text('other_verified_outcome')->nullable(); $t->string('yiw_before')->nullable(); $t->text('transformation_objective')->nullable();
   $t->string('after_work_status')->nullable(); $t->string('after_work_pathway')->nullable(); $t->boolean('profile_complete')->default(false);
   $t->boolean('learning_access_paid')->default(false);
  });
  Schema::create('mel_records',function(Blueprint $t){$t->id();$t->string('category')->index();$t->json('data');$t->timestamps();});
  Schema::create('mel_documents',function(Blueprint $t){$t->id();$t->string('category');$t->string('document');$t->text('description')->nullable();$t->string('file_path');$t->unsignedBigInteger('size');$t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();$t->timestamps();});
 }
 public function down(): void {
  Schema::dropIfExists('mel_documents'); Schema::dropIfExists('mel_records');
  Schema::table('users',function(Blueprint $t){$t->dropUnique(['learner_no']);$t->dropColumn(['learner_no','venture','enrollment_date','enrollment_category','gender','location','urban_rural','learner_age','refugee','settlement','pwd','impairment','education_level','learner_status','verified_outcomes','other_verified_outcome','yiw_before','transformation_objective','after_work_status','after_work_pathway','profile_complete','learning_access_paid']);});
 }
};
