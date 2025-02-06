<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hazem_orders_zatca', function (Blueprint $table) {
            $table->id();
            $table->morphs('orderable');
            $table->string('invoice_number');
            $table->string('uuid')->unique();
            $table->string('invoice_hash');
            $table->text('signed_invoice_xml');
            $table->string('status')->default('pending');
            $table->boolean('is_reported')->default(false);
            $table->boolean('is_cleared')->default(false);
            $table->json('warnings')->nullable();
            $table->json('errors')->nullable();
            $table->json('response')->nullable();
            $table->text('qr_code')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_number', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('hazem_orders_zatca');
    }
};
