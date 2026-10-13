<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

// plan.md D-10 / data-model.md §9: the initial admin is not seeded by the database — the
// application creates it on boot from ADMIN_USERNAME / ADMIN_PASSWORD, because only the
// hashing port can produce the hash. Idempotent: safe to run on every container start.
class EnsureAdminUser extends Command
{
    protected $signature = 'app:ensure-admin';

    protected $description = 'Creates the initial admin user from ADMIN_USERNAME/ADMIN_PASSWORD if it does not exist yet';

    public function handle(): int
    {
        $username = strtolower(trim((string) config('library.admin_username')));
        $password = (string) config('library.admin_password');

        if ($username === '' || $password === '') {
            $this->error('ADMIN_USERNAME / ADMIN_PASSWORD are not set.');

            return self::FAILURE;
        }

        $user = User::where('username', $username)->first();

        if ($user) {
            $this->info("Admin '{$username}' already exists.");

            return self::SUCCESS;
        }

        User::create([
            'username' => $username,
            'password_hash' => Hash::make($password),
            'role' => User::ROLE_ADMIN,
        ]);

        $this->info("Admin '{$username}' created.");

        return self::SUCCESS;
    }
}
