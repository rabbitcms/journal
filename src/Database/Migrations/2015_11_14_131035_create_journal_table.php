<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Class CreateJournalTable.
 */
class CreateJournalTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('journal', function (Blueprint $table) {
            $table->increments('id');
            $table->nullableMorphs('owner');
            $table->unsignedInteger('entity_id')->nullable()->default(null);
            $table->string('entity_type');
            $table->index(['entity_id', 'entity_type']);
            $table->enum('type', ['created', 'updated', 'deleted', 'restored']);
            $table->longText('previous')->nullable()->default(null);
            $table->longText('current')->nullable()->default(null);
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('journal');
    }
}
