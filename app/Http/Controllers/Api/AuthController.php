<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Jwt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // spec.md AC-01.1/AC-01.2: valid credentials -> 8h token; invalid -> 401 without
    // revealing whether it was the username or the password.
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', strtolower(trim($data['username'])))->first();

        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            throw new ApiException('INVALID_CREDENTIALS', 'Usuario o contraseña inválidos.', 401);
        }

        return response()->json(Jwt::issue($user->id, $user->role));
    }
}
