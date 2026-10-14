<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>American Shelf Abobo — Plateforme de Gestion de Bibliothèque</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0f1d;
            --bg-secondary: #0f172a;
            --bg-card: rgba(15, 23, 42, 0.75);
            --bg-card-hover: rgba(30, 41, 59, 0.85);
            --border-card: rgba(255, 255, 255, 0.08);
            --border-card-hover: rgba(59, 130, 246, 0.4);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --primary-glow: rgba(59, 130, 246, 0.35);
            --accent-gold: #f59e0b;
            --accent-emerald: #10b981;
            --accent-purple: #8b5cf6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(59, 130, 246, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 85% 25%, rgba(139, 92, 246, 0.1) 0%, transparent 45%),
                radial-gradient(circle at 50% 85%, rgba(245, 158, 11, 0.08) 0%, transparent 50%);
            background-attachment: fixed;
        }

        h1, h2, h3, h4, .font-heading {
            font-family: 'Outfit', sans-serif;
        }

        /* Glassmorphism utility */
        .glass {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-card);
        }

        .glass-hover {
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .glass-hover:hover {
            background: var(--bg-card-hover);
            border-color: var(--border-card-hover);
            transform: translateY(-4px);
            box-shadow: 0 12px 30px -10px rgba(0, 0, 0, 0.5);
        }

        /* Navigation Header */
        header {
            position: sticky;
            top: 0;
            z-index: 50;
            border-bottom: 1px solid var(--border-card);
            background: rgba(10, 15, 29, 0.85);
            backdrop-filter: blur(12px);
        }

        .container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .nav-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 76px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #fff;
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #1d4ed8, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 0 20px var(--primary-glow);
        }

        .brand-text h1 {
            font-size: 19px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .brand-text span {
            font-size: 11px;
            color: var(--accent-gold);
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .btn-outline {
            background: rgba(255, 255, 255, 0.05);
            color: #f1f5f9;
            border-color: var(--border-card);
        }
        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #ffffff;
            box-shadow: 0 4px 15px var(--primary-glow);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
        }

        .btn-gold {
            background: linear-gradient(135deg, #d97706, #f59e0b);
            color: #0f172a;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #b45309, #d97706);
            color: #fff;
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            padding: 90px 0 60px;
            text-align: center;
            position: relative;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            background: rgba(59, 130, 246, 0.12);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            margin-bottom: 24px;
        }

        .hero-title {
            font-size: clamp(38px, 6vw, 68px);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.03em;
            margin-bottom: 24px;
            background: linear-gradient(135deg, #ffffff 40%, #93c5fd 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: clamp(16px, 2vw, 20px);
            color: var(--text-muted);
            max-width: 780px;
            margin: 0 auto 36px;
            font-weight: 400;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 50px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }

        .stat-card {
            padding: 24px;
            border-radius: 16px;
            text-align: left;
        }

        .stat-num {
            font-size: 38px;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #60a5fa, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
            margin-bottom: 8px;
        }

        .stat-card:nth-child(2) .stat-num {
            background: linear-gradient(135deg, #34d399, #10b981);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card:nth-child(3) .stat-num {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card:nth-child(4) .stat-num {
            background: linear-gradient(135deg, #a78bfa, #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            font-size: 14px;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 4px;
        }

        .stat-desc {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Section Layout */
        .section {
            padding: 80px 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-tag {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--primary);
            margin-bottom: 10px;
            display: block;
        }

        .section-title {
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 16px;
        }

        .section-desc {
            font-size: 16px;
            color: var(--text-muted);
            max-width: 650px;
            margin: 0 auto;
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 24px;
        }

        .feature-card {
            padding: 32px;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
        }

        .feature-icon-wrapper {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 20px;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .feature-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #ffffff;
        }

        .feature-desc {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .feature-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
            color: #cbd5e1;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .feature-list li::before {
            content: "✓";
            color: var(--accent-emerald);
            font-weight: bold;
        }

        /* Architecture Banner */
        .arch-box {
            padding: 40px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.6) 100%);
            border: 1px solid var(--border-card);
            margin-top: 30px;
        }

        .stack-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
        }

        .stack-badge {
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 13px;
            font-weight: 600;
            color: #e2e8f0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* CTA Section */
        .cta-section {
            padding: 80px 0;
            text-align: center;
        }

        .cta-box {
            padding: 60px 40px;
            border-radius: 24px;
            background: radial-gradient(circle at center, rgba(37, 99, 235, 0.15) 0%, rgba(15, 23, 42, 0.8) 100%);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        /* Footer */
        footer {
            border-top: 1px solid var(--border-card);
            padding: 40px 0;
            background: #080c18;
            color: var(--text-muted);
            font-size: 14px;
        }

        .footer-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-links {
            display: flex;
            gap: 24px;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: #fff;
        }

        @media (max-width: 768px) {
            .nav-content {
                flex-direction: column;
                height: auto;
                padding: 16px 0;
                gap: 16px;
            }
            .hero-title {
                font-size: 34px;
            }
            .features-grid {
                grid-template-columns: 1fr;
            }
            .footer-content {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header>
        <div class="container">
            <div class="nav-content">
                <a href="/" class="brand-logo">
                    <div class="logo-icon">📚</div>
                    <div class="brand-text">
                        <h1>American Shelf Abobo</h1>
                        <span>Espace Numérique Documentaire</span>
                    </div>
                </a>

                <nav class="nav-links">
                    <a href="#features" class="btn btn-outline">Fonctionnalités</a>
                    <a href="#architecture" class="btn btn-outline">Architecture</a>
                    <a href="/api/documentation" class="btn btn-primary">
                        <span>⚡ Swagger API</span>
                    </a>
                </nav>
            </div>
        </div>
    </header>
 
    <!-- Main Content -->
    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <div class="hero-badge">
                    <span>🏛️ Espace American Spaces Côte d'Ivoire</span>
                </div>
                
                <h1 class="hero-title">
                    Gestion de Bibliothèque Moderne & SaaS Multi-Tenant
                </h1>
                
                <p class="hero-subtitle">
                    Plateforme robuste développée avec <strong>Laravel 12</strong>, conçue pour orchestrer avec fluidité les fonds documentaires, la circulation des livres, les adhésions usagers et les statistiques d'audience de l'<strong>American Shelf d'Abobo</strong>.
                </p>

                <div class="hero-actions">
                    <a href="/api/documentation" class="btn btn-primary" style="padding: 14px 28px; font-size: 15px;">
                        <span>🚀 Explorer la documentation Swagger</span>
                    </a>
                    <a href="/docs/american-shelf.dto.ts" download class="btn btn-gold" style="padding: 14px 24px; font-size: 15px;">
                        <span>⚡ Télécharger DTOs TypeScript (Angular)</span>
                    </a>
                    <a href="/api/v1/health" target="_blank" class="btn btn-outline" style="padding: 14px 20px; font-size: 15px;">
                        <span>🟢 Diagnostic Health</span>
                    </a>
                </div>

                <!-- Stats Bar -->
                <div class="stats-grid">
                    <div class="stat-card glass glass-hover">
                        <div class="stat-num">20</div>
                        <div class="stat-label">Modules Métier</div>
                        <div class="stat-desc">Du catalogue aux réservations et journaux d'audit</div>
                    </div>
                    <div class="stat-card glass glass-hover">
                        <div class="stat-num">140</div>
                        <div class="stat-label">Endpoints REST</div>
                        <div class="stat-desc">API versionnée /api/v1 sous JWT & Tenant isolation</div>
                    </div>
                    <div class="stat-card glass glass-hover">
                        <div class="stat-num">157</div>
                        <div class="stat-label">Tests Automatisés</div>
                        <div class="stat-desc">785 assertions de validation avec 0 échec</div>
                    </div>
                    <div class="stat-card glass glass-hover">
                        <div class="stat-num">100%</div>
                        <div class="stat-label">Multi-Tenant Isolé</div>
                        <div class="stat-desc">Filtrage strict par site_id & protection anti-IDOR</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="section">
            <div class="container">
                <div class="section-header">
                    <span class="section-tag">Domaines Fonctionnels</span>
                    <h2 class="section-title">Une suite complète pour bibliothèques connectées</h2>
                    <p class="section-desc">
                        Tous les rouages de la gestion documentaire ont été digitalisés pour offrir un service d'excellence aux étudiants, chercheurs et gestionnaires.
                    </p>
                </div>

                <div class="features-grid">
                    <!-- Card 1 -->
                    <div class="feature-card glass glass-hover">
                        <div class="feature-icon-wrapper" style="background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.4);">
                            📖
                        </div>
                        <h3 class="feature-title">Fonds Documentaire & Exemplaires</h3>
                        <p class="feature-desc">
                            Gestion unitaire et en lot du catalogue. Distinction stricte entre la notice bibliographique et les exemplaires physiques étiquetés.
                        </p>
                        <ul class="feature-list">
                            <li>Notices : Titre, Auteur, ISBN, Année, Catégories</li>
                            <li>Exemplaires : Codes-barres, États physiques, Emplacements</li>
                            <li>Génération d'exemplaires en lot (Bulk creation)</li>
                            <li>Statuts dynamiques (Disponible, Emprunté, Consultation)</li>
                        </ul>
                    </div>

                    <!-- Card 2 -->
                    <div class="feature-card glass glass-hover">
                        <div class="feature-icon-wrapper" style="background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.4);">
                            👥
                        </div>
                        <h3 class="feature-title">Usagers & Adhésions Multi-Sites</h3>
                        <p class="feature-desc">
                            Architecture globale et multi-site. Une personne physique unique peut être affiliée à plusieurs sites avec des cartes de lecteur distinctes.
                        </p>
                        <ul class="feature-list">
                            <li>Typologie : Usager simple ou Adhérent</li>
                            <li>Numéros de cartes uniques par site</li>
                            <li>Validité, cotisations annuelles et renouvellements</li>
                            <li>Pointage et contrôle des présences en salle</li>
                        </ul>
                    </div>

                    <!-- Card 3 -->
                    <div class="feature-card glass glass-hover">
                        <div class="feature-icon-wrapper" style="background: rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.4);">
                            🔄
                        </div>
                        <h3 class="feature-title">Prêts, Consultations & Retours</h3>
                        <p class="feature-desc">
                            Flux de circulation sécurisés par transactions de base de données. Verrouillage pessimiste anti-concurrence sur les exemplaires.
                        </p>
                        <ul class="feature-list">
                            <li>Calcul des dates limites selon la politique du site</li>
                            <li>Gel de configuration historique sur chaque prêt</li>
                            <li>Détection des retards & calcul des pénalités journalières</li>
                            <li>Consultations sur place avec clôture horodatée</li>
                        </ul>
                    </div>

                    <!-- Card 4 -->
                    <div class="feature-card glass glass-hover">
                        <div class="feature-icon-wrapper" style="background: rgba(139, 92, 246, 0.15); border-color: rgba(139, 92, 246, 0.4);">
                            📅
                        </div>
                        <h3 class="feature-title">Réservations & Notifications</h3>
                        <p class="feature-desc">
                            File d'attente automatisée pour les ouvrages plébiscités avec gestion des délais de validité et alertes automatiques.
                        </p>
                        <ul class="feature-list">
                            <li>Réservation préalable conditionnée par le statut d'adhérent</li>
                            <li>Expiration automatique des réservations non honorées</li>
                            <li>Notifications par e-mail et enregistrement en base</li>
                            <li>Alertes de retard de prêt et rappels d'échéance</li>
                        </ul>
                    </div>

                    <!-- Card 5 -->
                    <div class="feature-card glass glass-hover">
                        <div class="feature-icon-wrapper" style="background: rgba(236, 72, 153, 0.15); border-color: rgba(236, 72, 153, 0.4);">
                            📊
                        </div>
                        <h3 class="feature-title">Tableau de Bord & Statistiques</h3>
                        <p class="feature-desc">
                            Indicateurs clés calculés dynamiquement sur la base opérationnelle, sans table statistique redondante.
                        </p>
                        <ul class="feature-list">
                            <li>Filtrage temporel paramétrable (date_debut / date_fin)</li>
                            <li>Indicateurs documents, adhésions, présences et prêts</li>
                            <li>Top 5 des ouvrages les plus empruntés du site</li>
                            <li>Répartition des flux par catégorie documentaire</li>
                        </ul>
                    </div>

                    <!-- Card 6 -->
                    <div class="feature-card glass glass-hover">
                        <div class="feature-icon-wrapper" style="background: rgba(14, 165, 233, 0.15); border-color: rgba(14, 165, 233, 0.4);">
                            🛡️
                        </div>
                        <h3 class="feature-title">Sécurité & Piste d'Audit</h3>
                        <p class="feature-desc">
                            Sécurité de niveau institutionnel avec isolation tenant, contrôles d'accès granulaires et immuabilité des journaux.
                        </p>
                        <ul class="feature-list">
                            <li>Double Guard JWT étanche (Admin & Usager)</li>
                            <li>Piste d'audit JournalActivite strictement immuable</li>
                            <li>Protection anti-IDOR avec code HTTP 404 unifié</li>
                            <li>Sauvegardes du site avec suppression logique & restauration</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Architecture & Tech Stack -->
        <section id="architecture" class="section" style="padding-top: 0;">
            <div class="container">
                <div class="arch-box">
                    <span class="section-tag">Socle Technologique</span>
                    <h2 class="section-title">Architecture d'Entreprise Éprouvée</h2>
                    <p class="section-desc" style="margin: 0; text-align: left;">
                        Le backend suit scrupuleusement le patron en couches Laravel : 
                        <code>Request → Middleware → Form Request → Controller → Service → Model/ORM → Resource → JSON</code>.
                    </p>

                    <div class="stack-badges">
                        <span class="stack-badge">⚡ Laravel 12</span>
                        <span class="stack-badge">🐘 PHP 8.2+</span>
                        <span class="stack-badge">🗄️ MySQL</span>
                        <span class="stack-badge">🔐 JWT Auth</span>
                        <span class="stack-badge">🏢 Multi-Tenant (site_id)</span>
                        <span class="stack-badge">🛡️ Anti-IDOR (404)</span>
                        <span class="stack-badge">📑 OpenAPI 3.0</span>
                        <span class="stack-badge">🅰️ Angular DTOs</span>
                        <span class="stack-badge">🧪 PHPUnit 11</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta-section">
            <div class="container">
                <div class="cta-box glass">
                    <h2 class="section-title" style="margin-bottom: 16px;">Prêt à consommer l'API avec Angular ?</h2>
                    <p class="section-desc" style="margin-bottom: 36px;">
                        Toutes les définitions TypeScript et la console interactive Swagger sont disponibles pour accélérer le développement frontend.
                    </p>
                    <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                        <a href="/api/documentation" class="btn btn-primary" style="padding: 14px 28px; font-size: 15px;">
                            <span>Consulter Swagger UI (/docs)</span>
                        </a>
                        <a href="/docs/american-shelf.dto.ts" download class="btn btn-gold" style="padding: 14px 24px; font-size: 15px;">
                            <span>Télécharger american-shelf.dto.ts</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div>
                    <strong style="color: #fff; font-size: 15px;">American Shelf Abobo</strong>
                    <p style="margin-top: 4px; font-size: 13px;">Université Nangui Abrogoua • Réseau American Spaces Côte d'Ivoire</p>
                </div>
                <div class="footer-links">
                    <a href="/api/documentation">Swagger UI</a>
                    <a href="/docs/openapi.json">OpenAPI Spec</a>
                    <a href="/docs/american-shelf.dto.ts">DTOs Angular</a>
                    <a href="/api/v1/health">API Health</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
