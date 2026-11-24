<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('or_requests', function (Blueprint $table) {
            $table->id();
            $table->string('document_no')->unique();
            $table->string('branch_name');
            $table->string('customer_code')->nullable();
            $table->string('customer_name');
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('category');
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('info'); // payment, stock, shipping, or_approved
            $table->string('title');
            $table->string('description');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('products');
        Schema::dropIfExists('or_requests');
    }
};
