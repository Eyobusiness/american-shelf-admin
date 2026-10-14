<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>American Shelf Abobo — Swagger API & DTOs</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@5.11.0/favicon-32x32.png" sizes="32x32" />
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1e293b;
        }
        .header-bar {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-title h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        .badge {
            background-color: #3b82f6;
            color: #ffffff;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 9999px;
            text-transform: uppercase;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-action:hover {
            background-color: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
        }
        .btn-action-primary {
            background-color: #f59e0b;
            color: #0f172a;
            font-weight: 600;
            border-color: #f59e0b;
        }
        .btn-action-primary:hover {
            background-color: #d97706;
            color: #ffffff;
            border-color: #d97706;
        }
        #swagger-ui {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .swagger-ui .topbar {
            display: none;
        }
        .swagger-ui .info {
            margin: 20px 0;
        }
        .swagger-ui .info .title {
            font-size: 28px;
            color: #0f172a;
        }
        .swagger-ui .scheme-container {
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>
    <header class="header-bar">
        <div class="header-title">
            <span style="font-size: 24px;">📚</span>
            <div>
                <h1>American Shelf Abobo</h1>
                <p style="font-size: 12px; color: #94a3b8;">Backend Laravel 12 — API REST v1 & Documentation Swagger</p>
            </div>
            <span class="badge">v1.0.0</span>
        </div>
        <div class="header-actions">
            <a href="/" class="btn-action">
                🏠 Présentation
            </a>
            <a href="/docs/american-shelf.dto.ts" download class="btn-action btn-action-primary">
                ⚡ Télécharger DTOs TypeScript (Angular)
            </a>
            <a href="/docs/openapi.json" target="_blank" class="btn-action">
                📄 OpenAPI JSON
            </a>
            <a href="/api/v1/health" target="_blank" class="btn-action">
                🟢 API Health
            </a>
        </div>
    </header>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            window.ui = SwaggerUIBundle({
                url: "/docs/openapi.json",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "BaseLayout",
                persistAuthorization: true,
                displayRequestDuration: true,
                filter: true,
                docExpansion: "none",
                defaultModelsExpandDepth: 2,
                defaultModelExpandDepth: 2,
                tryItOutEnabled: true,
            });
        };
    </script>
</body>
</html>
