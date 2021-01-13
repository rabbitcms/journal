<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateJournalTable extends Migration
{
    public function up(): void
    {
        Schema::create('journal', function (Blueprint $table) {
            $table->id('id');
            $table->nullableMorphs('owner');
            $table->unsignedBigInteger('entity_id')->nullable()->default(null);
            $table->string('entity_type');
            $table->index(['entity_id', 'entity_type']);
            $table->enum('type', ['created', 'updated', 'deleted', 'restored', 'forceDeleted']);
            $table->longText('previous')->nullable()->default(null);
            $table->longText('current')->nullable()->default(null);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal');
    }
}
