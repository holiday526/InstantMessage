<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHmacColumnToUserKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_keys', function (Blueprint $table) {
            //
            $table->string('hmac_key')->after('ciphertext_blob');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_keys', function (Blueprint $table) {
            //
            $table->dropColumn('hmac_key');
        });
    }
}
