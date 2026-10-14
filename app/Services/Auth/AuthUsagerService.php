<?php

namespace App\Services\Auth;

use App\Models\Usager;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthUsagerService
{
    /**
     * Authenticate a public usager and return JWT token.
     *
     * @param string $identifiant
     * @param string $password
     * @return array<string, mixed>
     * @throws AuthenticationException
     */
    public function login(string $identifiant, string $password): array
    {
        /** @var Usager|null $usager */
        $usager = Usager::query()
            ->where(function ($query) use ($identifiant) {
                $query->where('email', $identifiant)
                    ->orWhere('numero', $identifiant);
            })
            ->with(['usagerSites.site'])
            ->first();

        if (!$usager || !$usager->mot_de_passe || !Hash::check($password, $usager->mot_de_passe)) {
            throw new AuthenticationException('Identifiants usager invalides.');
        }

        // Génération du token JWT via le guard 'usagers'
        /** @var string|false $token */
        $token = Auth::guard('usagers')->login($usager);

        if (!$token) {
            throw new AuthenticationException('Impossible de générer le jeton de sécurité.');
        }

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
            'usager' => $usager,
        ];
    }

    /**
     * Get authenticated public usager profile.
     *
     * @return Usager|null
     */
    public function me(): ?Usager
    {
        /** @var Usager|null $usager */
        $usager = Auth::guard('usagers')->user();

        if ($usager) {
            $usager->loadMissing(['usagerSites.site']);
        }

        return $usager;
    }

    /**
     * Refresh the JWT token for usager.
     *
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        $newToken = Auth::guard('usagers')->refresh();

        return [
            'access_token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
            'usager' => $this->me(),
        ];
    }

    /**
     * Logout and invalidate the usager JWT token.
     */
    public function logout(): void
    {
        Auth::guard('usagers')->logout();
    }
}
