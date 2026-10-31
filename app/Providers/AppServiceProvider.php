<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Détection automatique de l'URL de base (notamment si le backend est dans un sous-dossier /admin sur le serveur)
        if (! $this->app->runningInConsole() && request()->getHost()) {
            $rootUrl = rtrim(request()->getSchemeAndHttpHost(), '/');
            $scriptName = str_replace('\\', '/', request()->server('SCRIPT_NAME', ''));
            $requestUri = request()->getRequestUri();

            if (str_starts_with($scriptName, '/admin/') || str_starts_with($requestUri, '/admin/') || $requestUri === '/admin') {
                $rootUrl .= '/admin';
            }

            config([
                'app.url' => $rootUrl,
                'filesystems.disks.public.url' => $rootUrl . '/storage',
            ]);
        }
    }
}
