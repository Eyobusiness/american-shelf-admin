<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentationTest extends TestCase
{
    public function test_can_access_swagger_ui_dashboard(): void
    {
        $response = $this->get('/docs');
        $response->assertStatus(200);
        $response->assertSee('SwaggerUIBundle');
        $response->assertSee('American Shelf Abobo');
    }

    public function test_can_access_swagger_via_alternate_routes(): void
    {
        $this->get('/swagger')->assertStatus(200);
        $this->get('/api/documentation')->assertStatus(200);
    }

    public function test_can_fetch_openapi_json_specification(): void
    {
        $response = $this->get('/docs/openapi.json');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        $data = $response->json();
        $this->assertEquals('3.0.3', $data['openapi']);
        $this->assertArrayHasKey('paths', $data);
        $this->assertArrayHasKey('components', $data);
        $this->assertArrayHasKey('schemas', $data['components']);
        $this->assertArrayHasKey('/dashboard', $data['paths']);
        $this->assertArrayHasKey('/journal-activites', $data['paths']);
    }

    public function test_can_download_typescript_dto_file(): void
    {
        $response = $this->get('/docs/american-shelf.dto.ts');
        $response->assertStatus(200);
        $response->assertSee('ApiResponse<T>', false);
        $response->assertSee('DashboardMetrics', false);
        $response->assertSee('ExemplaireStatut', false);
    }

    public function test_can_access_presentation_page(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('American Shelf Abobo');
        $response->assertSee('Gestion de Bibliothèque Moderne');
        $response->assertSee('20');
        $response->assertSee('140');

        $altResponse = $this->get('/presentation');
        $altResponse->assertStatus(200);
        $altResponse->assertSee('American Shelf Abobo');
    }
}
