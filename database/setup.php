<?php
declare(strict_types=1);

require_once __DIR__ . '/../engine/Bootstrap.php';

\Oshim\Bootstrap::boot(dirname(__DIR__));

use Oshim\Database\Schema\Schema;
use Oshim\Database\Schema\Blueprint;

echo "Setting up database...\n";

// Users Table
if (!Schema::connection()->hasTable('users')) {
    Schema::connection()->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });
    echo "Created users table.\n";
} else {
    echo "users table already exists.\n";
}

// Documents Table
if (!Schema::connection()->hasTable('documents')) {
    Schema::connection()->create('documents', function (Blueprint $table) {
        $table->id();
        $table->integer('user_id'); // foreign key
        $table->string('title');
        $table->text('content')->nullable();
        $table->text('prompt')->nullable();
        $table->timestamps();
    });
    echo "Created documents table.\n";
} else {
    echo "documents table already exists.\n";
}

echo "Database setup complete.\n";
