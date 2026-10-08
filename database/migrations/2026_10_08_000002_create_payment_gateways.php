<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {Schema::create('payment_gateways',function(Blueprint $t){$t->id();$t->string('name');$t->string('provider_type');$t->string('provider')->nullable();$t->string('account_name')->nullable();$t->string('account_number')->nullable();$t->string('merchant_code')->nullable();$t->string('currency',8)->default('UGX');$t->decimal('amount',14,2)->nullable();$t->text('instructions')->nullable();$t->boolean('active')->default(true)->index();$t->timestamps();});}
 public function down(): void {Schema::dropIfExists('payment_gateways');}
};
