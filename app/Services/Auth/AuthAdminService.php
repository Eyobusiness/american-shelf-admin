<?php

namespace App\Services\Auth;

use App\Models\Utilisateur;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthAdminService
{
    /**
     * Authenticate an administrative user and return JWT token with metadata.
     *
     * @param string $email
     * @param string $password
     * @param string|null $siteId
     * @return array<string, mixed>
     * @throws AuthenticationException
     */
    public function login(string $email, string $password, ?string $siteId = null): array
    {
        $query = Utilisateur::query()->where('email', $email);

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        /** @var Utilisateur|null $user */
        $user = $query->with(['site', 'role.permissions'])->first();

        if (!$user || !Hash::check($password, $user->mot_de_passe)) {
            throw new AuthenticationException('Identifiants de connexion invalides.');
        }

        if ($user->statut !== 'ACTIF') {
            throw new AuthenticationException('Votre compte administrateur est inactif ou suspendu.');
        }

        if ($user->site && $user->site->statut !== 'ACTIF') {
            throw new AuthenticationException('Le site rattaché à votre compte est actuellement inactif.');
        }

        // Génération du token JWT via le guard 'api'
        /** @var string|false $token */
        $token = Auth::guard('api')->login($user);

        if (!$token) {
            throw new AuthenticationException('Impossible de générer le jeton de sécurité.');
        }

        // Mise à jour de la date de dernière connexion sans déclencher d'évènements
        $user->timestamps = false;
        $user->derniere_connexion = now();
        $user->saveQuietly();

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
            'utilisateur' => $user,
        ];
    }

    /**
     * Get authenticated administrative user profile.
     *
     * @return Utilisateur|null
     */
    public function me(): ?Utilisateur
    {
        /** @var Utilisateur|null $user */
        $user = Auth::guard('api')->user();

        if ($user) {
            $user->loadMissing(['site', 'role.permissions']);
        }

        return $user;
    }

    /**
     * Mettre à jour les informations du profil connecté.
     *
     * @param array $data
     * @return Utilisateur
     * @throws AuthenticationException
     */
    public function updateProfile(array $data): Utilisateur
    {
        /** @var Utilisateur|null $user */
        $user = Auth::guard('api')->user();

        if (!$user) {
            throw new AuthenticationException('Utilisateur non connecté.');
        }

        $user->fill([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'telephone' => $data['telephone'] ?? null,
            'email' => $data['email'],
        ]);

        $user->save();
        $user->loadMissing(['site', 'role.permissions']);

        return $user;
    }

    /**
     * Modifier de manière sécurisée le mot de passe de l'administrateur connecté.
     *
     * @param string $currentPassword
     * @param string $newPassword
     * @return Utilisateur
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function updatePassword(string $currentPassword, string $newPassword): Utilisateur
    {
        /** @var Utilisateur|null $user */
        $user = Auth::guard('api')->user();

        if (!$user) {
            throw new AuthenticationException('Utilisateur non connecté.');
        }

        if (!Hash::check($currentPassword, $user->mot_de_passe)) {
            throw ValidationException::withMessages([
                'mot_de_passe_actuel' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->mot_de_passe = Hash::make($newPassword);
        $user->save();

        return $user;
    }

    /**
     * Refresh the JWT token.
     *
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        $newToken = Auth::guard('api')->refresh();

        return [
            'access_token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
            'utilisateur' => $this->me(),
        ];
    }

    /**
     * Logout and invalidate the JWT token.
     */
    public function logout(): void
    {
        Auth::guard('api')->logout();
    }
}