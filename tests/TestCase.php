<?php

namespace Tests;

use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return JWTGuard
     */
    protected function jwtGuard()
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        return $guard;
    }

    /**
     * Génère un jeton JWT valide pour un utilisateur donné avec typage JWTGuard.
     */
    protected function loginAs(Utilisateur $user): string
    {
        return (string) $this->jwtGuard()->login($user);
    }
}
