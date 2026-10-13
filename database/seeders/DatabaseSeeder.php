<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

// The category seed lives in the migration itself (data-model.md §9 / ADR-001).
// The admin user is created by `php artisan app:ensure-admin` (plan.md D-10).
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('app:ensure-admin');
    }
}
