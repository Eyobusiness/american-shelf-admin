<?php

namespace App\Services\Auth;

use App\Models\Utilisateur;

class PermissionService
{
    /**
     * Check if the user has a specific permission code.
     *
     * @param Utilisateur $user
     * @param string $permissionCode
     * @return bool
     */
    public function hasPermission(Utilisateur $user, string $permissionCode): bool
    {
        $role = $user->role;

        if (!$role) {
            return false;
        }

        // Super-administrateur global du site (code 'ADMIN' ou 'SUPERADMIN') a tous les droits
        if (in_array(strtoupper($role->code), ['ADMIN', 'SUPERADMIN', 'ADMINISTRATEUR'], true)) {
            return true;
        }

        $user->load('role.permissions');

        return $user->role?->permissions->contains('code', $permissionCode) ?? false;
    }

    /**
     * Check if the user has a specific role.
     *
     * @param Utilisateur $user
     * @param array<string>|string $roles
     * @return bool
     */
    public function hasRole(Utilisateur $user, array|string $roles): bool
    {
        $role = $user->role;

        if (!$role) {
            return false;
        }

        $rolesArray = is_array($roles) ? $roles : explode('|', $roles);
        $rolesUpper = array_map('strtoupper', $rolesArray);

        return in_array(strtoupper($role->code), $rolesUpper, true);
    }

    /**
     * Check if the user has permission on a menu / submenu / action.
     *
     * @param Utilisateur $user
     * @param string $action
     * @param string|null $menuCode
     * @param string|null $sousMenuCode
     * @return bool
     */
    public function hasAction(Utilisateur $user, string $action, ?string $menuCode = null, ?string $sousMenuCode = null): bool
    {
        $role = $user->role;

        if (!$role) {
            return false;
        }

        if (in_array(strtoupper($role->code), ['ADMIN', 'SUPERADMIN', 'ADMINISTRATEUR'], true)) {
            return true;
        }

        $user->loadMissing(['role.permissions.menu', 'role.permissions.sousMenu']);

        return $role->permissions->contains(function ($permission) use ($action, $menuCode, $sousMenuCode) {
            $actionMatches = strtoupper($permission->action) === strtoupper($action);

            if (!$actionMatches) {
                return false;
            }

            if ($menuCode && (!$permission->menu || strtoupper($permission->menu->code) !== strtoupper($menuCode))) {
                return false;
            }

            if ($sousMenuCode && (!$permission->sousMenu || strtoupper($permission->sousMenu->code) !== strtoupper($sousMenuCode))) {
                return false;
            }

            return true;
        });
    }
}
