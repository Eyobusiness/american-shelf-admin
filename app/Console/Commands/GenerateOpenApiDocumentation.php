<?php

namespace App\Console\Commands;

use App\Services\Documentation\OpenApiSpecificationBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateOpenApiDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère la spécification OpenAPI 3.0 (Swagger) et les DTOs TypeScript';

    /**
     * Execute the console command.
     */
    public function handle(OpenApiSpecificationBuilder $builder): int
    {
        $this->info('Génération de la spécification OpenAPI 3.0...');

        $spec = $builder->build();
        $json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $docsDir = public_path('docs');
        if (!File::exists($docsDir)) {
            File::makeDirectory($docsDir, 0755, true);
        }

        $outputPath = $docsDir . DIRECTORY_SEPARATOR . 'openapi.json';
        File::put($outputPath, $json);

        $this->info("Spécification générée avec succès dans : {$outputPath}");

        return Command::SUCCESS;
    }
}
