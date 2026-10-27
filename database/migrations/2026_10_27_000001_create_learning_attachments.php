<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('learning_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 30)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('file_path');
            $table->string('original_name');
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_attachments');
    }
};
