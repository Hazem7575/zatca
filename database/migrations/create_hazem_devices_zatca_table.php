<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hazem_devices_zatca', function (Blueprint $table) {
            $table->id();
            $table->morphs('deviceable');
            $table->string('request_id');
            $table->string('disposition_message');
            $table->text('binary_security_token');
            $table->string('secret');
            $table->json('errors')->nullable();
            $table->text('data')->nullable();
            $table->text('public_key')->nullable();
            $table->text('private_key')->nullable();
            $table->text('csr_content')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hazem_devices_zatca');
    }
};
