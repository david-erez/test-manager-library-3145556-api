<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §3 "user". Postgres reserves the word "user"; Laravel quotes identifiers on its own.
// ck_user_role is intentionally left out here — data-model.md §4 marks it "pending (T-18)".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library.user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username', 120)->unique();
            $table->string('password_hash', 512);
            $table->string('role', 40);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library.user');
    }
};
