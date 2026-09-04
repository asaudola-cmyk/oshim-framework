<?php
declare(strict_types=1);

use Oshim\Database\Migrations\Migration;
use Oshim\Database\Schema\Blueprint;
use Oshim\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};