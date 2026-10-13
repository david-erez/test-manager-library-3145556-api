<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// data-model.md §0: schema "library". infra boots an empty engine; the schema itself is DDL
// owned by this migration (Article V / ADR-001).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS library');
    }

    public function down(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS library CASCADE');
    }
};
