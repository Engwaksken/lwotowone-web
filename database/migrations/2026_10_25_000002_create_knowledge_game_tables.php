<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('knowledge_game_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('topic', 32)->index();
            $table->text('question');
            $table->json('options');
            $table->string('correct_option', 1);
            $table->text('explanation');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('knowledge_game_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 32)->index();
            $table->json('question_ids');
            $table->json('answers')->nullable();
            $table->unsignedSmallInteger('score')->default(0);
            $table->unsignedSmallInteger('total_questions');
            $table->dateTime('completed_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_game_attempts');
        Schema::dropIfExists('knowledge_game_questions');
    }
};
