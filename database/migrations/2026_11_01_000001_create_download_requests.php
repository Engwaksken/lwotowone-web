<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Learners ask to download a lesson or resource file; staff approve a time- and count-limited download. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('download_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('item_type', 20);
            $t->unsignedBigInteger('item_id');
            $t->string('status', 20)->default('pending')->index();
            $t->text('message')->nullable();
            $t->text('decision_note')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->unsignedInteger('downloads_used')->default(0);
            $t->timestamp('last_downloaded_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'item_type', 'item_id']);
            $t->index(['item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_requests');
    }
};
