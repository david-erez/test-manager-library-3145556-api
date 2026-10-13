<?php

namespace App\Support;

use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;

// plan.md D-11: JWT HS256, 8h, sub + role, no personal data inside.
class Jwt
{
    public static function issue(string $userId, string $role): array
    {
        $ttlMinutes = (int) config('library.jwt_ttl_minutes');
        $now = time();

        $token = FirebaseJwt::encode([
            'sub' => $userId,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + $ttlMinutes * 60,
        ], config('library.jwt_secret'), 'HS256');

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttlMinutes * 60,
            'role' => $role,
        ];
    }

    /** @return object{sub:string, role:string} */
    public static function decode(string $token): object
    {
        return FirebaseJwt::decode($token, new Key(config('library.jwt_secret'), 'HS256'));
    }
}
