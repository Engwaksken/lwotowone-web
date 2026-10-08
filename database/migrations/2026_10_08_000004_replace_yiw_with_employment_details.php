<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::table('users',function(Blueprint $t){$t->dropColumn('yiw_before');$t->boolean('employed')->nullable();$t->string('employer_name')->nullable();});
 }
 public function down(): void {
  Schema::table('users',function(Blueprint $t){$t->dropColumn(['employed','employer_name']);$t->string('yiw_before')->nullable();});
 }
};
