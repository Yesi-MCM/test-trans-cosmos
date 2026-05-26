<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use Illuminate\Support\Facades\Log;

class JwtService
{
    protected string $secret;
    protected string $algo = 'HS256';

    public function __construct()
    {
        // Use JWT_SECRET from env, fallback to a substring of APP_KEY
        $this->secret = config('auth.jwt_secret') ?: config('app.key') ?: 'fallback-secret-key-1234567890!';
    }

    /**
     * Encode a payload into a JWT token.
     *
     * @param array $payload
     * @param int $ttl Minutes to live
     * @return string
     */
    public function encode(array $payload, int $ttl = 120): string
    {
        $currentTime = time();
        $fullPayload = array_merge([
            'iss' => config('app.url', 'http://localhost'),
            'iat' => $currentTime,
            'nbf' => $currentTime,
            'exp' => $currentTime + ($ttl * 60),
        ], $payload);

        return JWT::encode($fullPayload, $this->secret, $this->algo);
    }

    /**
     * Decode and validate a JWT token.
     *
     * @param string $token
     * @return array|null
     */
    public function decode(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));
            return (array) $decoded;
        } catch (Exception $e) {
            Log::debug('JWT Decode Failed: ' . $e->getMessage());
            if (app()->environment('testing')) {
                dd([
                    'message' => 'JWT Decode Failed: ' . $e->getMessage(),
                    'secret' => $this->secret,
                    'token' => $token,
                ]);
            }
            return null;
        }
    }
}
