<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §3 "book" + §4 constraints marked "engine".
// ck_book_total_positive is intentionally left out — data-model.md §4 marks it "pending (T-18)".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library.book', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 250);
            $table->string('author', 200);
            $table->char('isbn', 13);
            $table->uuid('category_id');
            $table->integer('total_copies');
            $table->integer('available_copies');
            $table->string('cover_key', 512)->nullable();
            $table->timestampTz('deleted_at')->nullable();

            $table->foreign('category_id')->references('id')->on('library.category')->restrictOnDelete();
        });

        // 0 <= available_copies <= total_copies — last line of defense for concurrency (ADR-002).
        DB::statement('ALTER TABLE library.book ADD CONSTRAINT ck_book_available_range
            CHECK (available_copies >= 0 AND available_copies <= total_copies)');

        // isbn unique among active books only (ADR-003): a soft-deleted book frees its ISBN.
        DB::statement('CREATE UNIQUE INDEX uq_book_isbn_active ON library.book (isbn)
            WHERE deleted_at IS NULL');

        // Q1 access pattern: search by category + title, active books only.
        DB::statement('CREATE INDEX ix_book_category_title_active ON library.book (category_id, title)
            WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('library.book');
    }
};
