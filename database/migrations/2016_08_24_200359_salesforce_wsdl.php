<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SalesforceWsdl extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesforce_db_config', function (Blueprint $t) {
            $t->longText('wsdl')->nullable();
            $t->string('version') ->nullable();
            $t->integer('oauth_service_id')->unsigned()->nullable();
            $t->foreign('oauth_service_id')->references('service_id')->on('oauth_config');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('salesforce_db_config', function (Blueprint $t) {
            $t->dropColumn('wsdl');
            $t->dropColumn('version');
            $t->dropColumn('oauth_service_id');
        });
    }
}
