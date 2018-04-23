<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMedialibraryAttachableIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('medialibrary_attachable', function (Blueprint $table) {
            $table->index(['attachable_id', 'attachable_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('medialibrary_attachable', function (Blueprint $table) {
            $table->dropIndex('medialibrary_attachable_attachable_id_attachable_type_index');
        });
    }
}
