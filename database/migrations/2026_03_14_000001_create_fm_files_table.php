<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fm_files')) {
            Schema::create('fm_files', function (Blueprint $table) {
                $table->id();
                $table->string('disk', 50)->default('public');
                $table->string('folder_path');
                $table->string('relative_path');
                $table->string('filename');
                $table->string('original_name');
                $table->string('extension', 20);
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size')->default(0);
                $table->boolean('has_thumbnail')->default(false);
                $table->boolean('is_trashed')->default(false);
                $table->timestamp('trashed_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['folder_path', 'relative_path']);
                $table->index(['folder_path', 'is_trashed']);
                $table->index('is_trashed');
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fm_files');
    }
};
