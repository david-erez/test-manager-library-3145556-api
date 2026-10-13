<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §3 "loan". A loan is an immutable fact (architecture-overview.md idea #1).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library.loan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('member_id');
            $table->uuid('loaned_by_user_id');
            $table->timestampTz('loaned_at');

            $table->foreign('member_id')->references('id')->on('library.member')->restrictOnDelete();
            $table->foreign('loaned_by_user_id')->references('id')->on('library.user')->restrictOnDelete();
        });

        // Q6 access pattern: list loans by date range.
        DB::statement('CREATE INDEX ix_loan_loaned_at ON library.loan (loaned_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('library.loan');
    }
};
