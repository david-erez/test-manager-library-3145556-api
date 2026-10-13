<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // spec.md AC-02.1-02.3: admin creates a librarian; role is forced server-side; password
    // is never returned.
    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['librarian'])],
        ]);

        $username = strtolower(trim($data['username']));

        if (User::where('username', $username)->exists()) {
            throw new ApiException('USERNAME_TAKEN', 'Ese nombre de usuario ya existe.', 409);
        }

        $user = User::create([
            'username' => $username,
            'password_hash' => Hash::make($data['password']),
            'role' => User::ROLE_LIBRARIAN,
        ]);

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
        ], 201);
    }
}
