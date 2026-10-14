<?php

namespace App\Http\Controllers;

use App\Services\Documentation\OpenApiSpecificationBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentationController extends Controller
{
    /**
     * Affiche la page de présentation du projet American Shelf Abobo.
     */
    public function presentation()
    {
        return view('presentation');
    }

    /**
     * Affiche l'interface Swagger UI.
     */
    public function index()
    {
        return view('swagger');
    }

    /**
     * Retourne la spécification OpenAPI 3.0 en format JSON.
     */
    public function openapi(OpenApiSpecificationBuilder $builder): Response
    {
        $path = public_path('docs/openapi.json');

        if (File::exists($path)) {
            $content = File::get($path);
        } else {
            $content = json_encode($builder->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return response($content, 200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Téléchargement du fichier de DTOs TypeScript pour Angular.
     */
    public function dto(): Response
    {
        $path = public_path('docs/american-shelf.dto.ts');

        if (!File::exists($path)) {
            $path = resource_path('ts/american-shelf.dto.ts');
        }

        $content = File::exists($path) ? File::get($path) : '';

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="american-shelf.dto.ts"',
        ]);
    }
}
