<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use App\Services\JwtService;

use Illuminate\Support\Facades\Log;

class JwtGuard implements Guard
{
    protected Request $request;
    protected UserProvider $provider;
    protected JwtService $jwtService;
    protected ?Authenticatable $user = null;

    public function __construct(UserProvider $provider, Request $request, JwtService $jwtService)
    {
        $this->provider = $provider;
        $this->request = $request;
        $this->jwtService = $jwtService;
    }

    /**
     * Get the current request instance.
     */
    protected function getRequest(): Request
    {
        return app('request') ?: $this->request;
    }

    public function check(): bool
    {
        return ! is_null($this->user());
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $request = $this->getRequest();
        $token = $this->getTokenFromRequest($request);

        if (empty($token)) {
            return null;
        }

        $payload = $this->jwtService->decode($token);

        if (!$payload || !isset($payload['sub'])) {
            return null;
        }

        $user = $this->provider->retrieveById($payload['sub']);

        if (!$user) {
            return null;
        }

        $this->user = $user;
        return $this->user;
    }

    public function id()
    {
        if ($user = $this->user()) {
            return $user->getAuthIdentifier();
        }
        return null;
    }

    public function validate(array $credentials = []): bool
    {
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return false;
        }

        $user = $this->provider->retrieveByCredentials($credentials);

        if (!$user) {
            return false;
        }

        return $this->provider->validateCredentials($user, $credentials);
    }

    public function hasUser(): bool
    {
        return ! is_null($this->user);
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    /**
     * Set the current request instance.
     */
    public function setRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    protected function getTokenFromRequest(Request $request): ?string
    {
        // 1. Try Authorization header: Bearer <token>
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        // 2. Try query parameter: ?token=<token>
        $queryToken = $request->query('token');
        if ($queryToken) {
            return $queryToken;
        }

        // 3. Try cookie: token=<token>
        return $request->cookie('token');
    }
}
