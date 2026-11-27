<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_cab', function (Blueprint $table) {
            $table->string('kd_cab', 10)->primary();
            $table->string('nm_cab');
        });

        Schema::create('or130a', function (Blueprint $table) {
            $table->id();
            $table->string('so_noso')->unique();
            $table->string('so_nocust');
            $table->string('so_name');
            $table->string('so_kota')->nullable();
            $table->unsignedBigInteger('so_harga')->default(0);
            $table->date('so_hdate');
            $table->date('so_dtso');
            $table->string('so_cekstat', 10)->nullable()->default('');
            $table->string('so_status', 5)->default('@');
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('or131a', function (Blueprint $table) {
            $table->id();
            $table->string('so_noso')->index();
            $table->string('so_kode')->nullable();
            $table->unsignedInteger('so_qty')->default(1);
            $table->unsignedBigInteger('so_harga')->default(0);
            $table->timestamps();
        });

        Schema::create('ar010a', function (Blueprint $table) {
            $table->string('mp_nocust')->primary();
            $table->string('mp_custno')->nullable()->index();
            $table->unsignedBigInteger('mp_t_cost')->default(0);
            $table->timestamps();
        });

        Schema::create('sa131a', function (Blueprint $table) {
            $table->id();
            $table->string('do_grdo', 10);
            $table->string('do_nocust')->index();
            $table->string('do_nofak')->nullable()->default('');
            $table->string('do_status', 5)->nullable()->default('');
            $table->string('do_statux', 5)->default('@');
            $table->unsignedInteger('do_qty')->default(0);
            $table->unsignedBigInteger('do_harga')->default(0);
            $table->timestamps();
        });

        Schema::create('ar020a', function (Blueprint $table) {
            $table->id();
            $table->string('fa_grfak', 10);
            $table->string('fa_nocust')->index();
            $table->string('fa_status', 5)->default('@');
            $table->unsignedBigInteger('fa_harga')->default(0);
            $table->unsignedBigInteger('fa_hgkas')->default(0);
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
            $table->string('type')->default('info');
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
        Schema::dropIfExists('ar020a');
        Schema::dropIfExists('sa131a');
        Schema::dropIfExists('ar010a');
        Schema::dropIfExists('or131a');
        Schema::dropIfExists('or130a');
        Schema::dropIfExists('tb_cab');
    }
};
