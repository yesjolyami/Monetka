<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('name');
            $table->string('emoji');
            $table->string('color');
            $table->timestamps();

            $table->index(['workspace_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
