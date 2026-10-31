<?php

use App\Http\Controllers\DocumentationController;
use Illuminate\Support\Facades\Route;

// Page de présentation du projet American Shelf Abobo
Route::get('/', [DocumentationController::class, 'presentation'])->name('home');
Route::get('/presentation', [DocumentationController::class, 'presentation'])->name('presentation');

// Documentation interactive Swagger UI
Route::get('/docs', [DocumentationController::class, 'index'])->name('docs');
Route::get('/api/documentation', [DocumentationController::class, 'index'])->name('api.documentation');
Route::get('/swagger', [DocumentationController::class, 'index'])->name('swagger');

// Spécification OpenAPI JSON & Fichier DTO TypeScript
Route::get('/docs/openapi.json', [DocumentationController::class, 'openapi'])->name('docs.openapi');
Route::get('/docs/american-shelf.dto.ts', [DocumentationController::class, 'dto'])->name('docs.dto');

// Route de secours pour servir les fichiers du storage public si le lien symbolique n'existe pas sur le serveur
Route::get('/storage/{path}', function (string $path) {
    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
    $disk = \Illuminate\Support\Facades\Storage::disk('public');

    if (! $disk->exists($path)) {
        abort(404);
    }

    return $disk->response($path);
})->where('path', '.*')->name('storage.serve');


