<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §3 "loan_item". book_title/isbn/category_name are frozen at loan time (BR-4, ADR-004)
// — category_name has no FK on purpose, so a later category rename never touches past loans.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library.loan_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loan_id');
            $table->uuid('book_id');
            $table->string('book_title', 250);
            $table->char('isbn', 13);
            $table->string('category_name', 120);
            $table->timestampTz('due_at');
            $table->timestampTz('returned_at')->nullable();

            $table->foreign('loan_id')->references('id')->on('library.loan')->cascadeOnDelete();
            $table->foreign('book_id')->references('id')->on('library.book')->restrictOnDelete();

            // A book cannot repeat within the same loan (AC-06.2).
            $table->unique(['loan_id', 'book_id']);
        });

        // Q7/Q8 access patterns: overdue lines are the unreturned ones past their due date.
        DB::statement('CREATE INDEX ix_loan_item_due_at_open ON library.loan_item (due_at)
            WHERE returned_at IS NULL');

        // Q3 access pattern: books by batch of ids, at lend time.
        DB::statement('CREATE INDEX ix_loan_item_book_id ON library.loan_item (book_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('library.loan_item');
    }
};
