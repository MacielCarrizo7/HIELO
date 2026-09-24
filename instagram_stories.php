<?php
/**
 * Módulo Generador de Historias para Instagram (Formato Vertical 9:16)
 * Conexión en tiempo real con Google Firestore para obtener precios y presentaciones de hielo.
 */
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";

requerirPaginaAutenticada(["admin", "vendedor"]);

$rol = $_SESSION["usuario_rol"] ?? "vendedor";
$nombreUsuario = trim(($_SESSION["usuario_nombre"] ?? "Usuario") . " " . ($_SESSION["usuario_apellido"] ?? ""));

$productosFirestore = [];
try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $productosRaw = $firestore->obtenerColeccion("productos");

    foreach ($productosRaw as $p) {
        $id = (int)($p["id"] ?? $p["_id"] ?? 0);
        if ($id <= 0) continue;
        
        $nombre = (string)($p["nombre"] ?? "Bolsa de Hielo");
        $precio = (float)($p["precio"] ?? $p["precio_venta"] ?? 0);
        $presentacion = (string)($p["presentacion"] ?? "unidad");
        $stock = (int)($p["stock"] ?? 0);
        $imagenUrl = (string)($p["imagen_url"] ?? "");

        $productosFirestore[] = [
            "id" => $id,
            "nombre" => $nombre,
            "precio" => $precio,
            "presentacion" => $presentacion,
            "stock" => $stock,
            "imagen_url" => $imagenUrl
        ];
    }

    // Ordenar por precio ascendente
    usort($productosFirestore, fn($a, $b) => $a["precio"] <=> $b["precio"]);
} catch (Throwable $e) {
    error_log("Error al cargar productos para stories: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Historias Instagram - Fábrica de Hielo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Inter:wght@400;500;600;700;800&family=Montserrat:ital,wght@0,800;0,900;1,900&display=swap" rel="stylesheet">
    
    <!-- html2canvas para renderizar y exportar en PNG HD -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <link rel="stylesheet" href="assets/estilos.css">
    <style>
        :root {
            --story-font-heading: 'Outfit', sans-serif;
            --story-font-impact: 'Montserrat', sans-serif;
            --story-font-body: 'Inter', sans-serif;
        }

        body {
            background-color: #f1f5f9;
            font-family: var(--story-font-body);
        }

        /* Simulador de Teléfono / Proporción 9:16 */
        .phone-mockup-wrapper {
            position: sticky;
            top: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .phone-frame {
            width: 380px;
            height: 675px;
            background: #090d16;
            border-radius: 42px;
            padding: 10px;
            box-shadow: 0 25px 60px -15px rgba(2, 132, 199, 0.3), 0 0 0 2px rgba(255,255,255,0.15), inset 0 0 10px rgba(0,0,0,0.8);
            position: relative;
            user-select: none;
        }

        .phone-screen {
            width: 100%;
            height: 100%;
            border-radius: 32px;
            overflow: hidden;
            position: relative;
            background: #0f172a;
        }

        /* =======================================================
           LIENZO DE HISTORIA (Proporción exacta 9:16)
        ======================================================= */
        .story-canvas {
            width: 100%;
            height: 100%;
            padding: 20px 18px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            color: #ffffff;
            box-sizing: border-box;
            background-image: url('assets/FONDO HISTORIA.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: rgba(0, 0, 0, 0.5);
            background-blend-mode: overlay;
            overflow: hidden;
        }

        /* Variantes de Tinte Overlay sobre la Imagen de Fondo */
        .overlay-standard {
            background-color: rgba(0, 0, 0, 0.5) !important;
        }
        .overlay-dark {
            background-color: rgba(0, 0, 0, 0.65) !important;
        }
        .overlay-glaciar {
            background-color: rgba(3, 75, 117, 0.55) !important;
        }
        .overlay-polar {
            background-color: rgba(15, 23, 42, 0.6) !important;
        }

        /* Escarcha y Brillo de Fondo */
        .frost-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            pointer-events: none;
            background-image: 
                radial-gradient(1.5px 1.5px at 20px 30px, #ffffff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 90px 80px, rgba(255,255,255,0.8), rgba(0,0,0,0)),
                radial-gradient(1px 1px at 160px 40px, #ffffff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 240px 120px, rgba(255,255,255,0.9), rgba(0,0,0,0)),
                radial-gradient(1.5px 1.5px at 300px 50px, #ffffff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 50px 220px, rgba(255,255,255,0.7), rgba(0,0,0,0)),
                radial-gradient(2.5px 2.5px at 320px 240px, rgba(255,255,255,0.85), rgba(0,0,0,0)),
                radial-gradient(1.5px 1.5px at 100px 340px, #ffffff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 280px 420px, rgba(255,255,255,0.7), rgba(0,0,0,0)),
                radial-gradient(1px 1px at 40px 520px, #ffffff, rgba(0,0,0,0));
            background-repeat: repeat;
            opacity: 0.45;
        }

        /* Badge Superior */
        .ice-badge-top {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            color: #ffffff;
        }

        /* =======================================================
           TÍTULO PRINCIPAL 3D CON RELIEVE Y BRILLO
        ======================================================= */
        .story-title-3d {
            font-family: var(--story-font-impact);
            font-size: 2.15rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            line-height: 1.05;
            margin: 4px 0 2px 0;
            text-align: center;
            background: linear-gradient(180deg, #ffffff 0%, #f0f9ff 30%, #bae6fd 65%, #38bdf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 0px #0284c7) 
                    drop-shadow(0 4px 8px rgba(0, 0, 0, 0.85))
                    drop-shadow(0 0 16px rgba(56, 189, 248, 0.9));
        }

        .story-subtitle {
            font-size: 0.78rem;
            text-align: center;
            color: #e0f2fe;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 4px;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.85), 0 0 10px rgba(0, 0, 0, 0.6);
        }

        /* =======================================================
           GRÁFICO CENTRAL: DOS BOLSAS DE HIELO TRANSPARENTES REALES
        ======================================================= */
        .ice-bags-stage {
            position: relative;
            width: 100%;
            height: 155px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 2px 0 6px;
        }

        .ice-bags-svg-wrapper {
            width: 100%;
            max-width: 320px;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.6));
        }

        /* =======================================================
           RECUADRO DE PRECIOS REALES FIRESTORE (Glassmorphism)
        ======================================================= */
        .story-pricing-box {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 16px;
            padding: 9px 11px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), inset 0 1px 1px rgba(255, 255, 255, 0.25);
            margin-bottom: 8px;
        }

        .story-pricing-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 4px;
            margin-bottom: 4px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .story-pricing-title {
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #7dd3fc;
            display: flex;
            align-items: center;
            gap: 4px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
        }

        .story-items-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
            max-height: 160px;
            overflow: hidden;
        }

        .story-price-row {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 10px;
            padding: 5px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.25);
        }

        .story-price-info {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .story-prod-name {
            font-family: var(--story-font-heading);
            font-size: 0.88rem;
            font-weight: 800;
            line-height: 1.1;
            color: #ffffff;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.7);
        }

        .story-prod-desc {
            font-size: 0.64rem;
            color: #bae6fd;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.6);
        }

        .story-prod-price {
            font-family: var(--story-font-impact);
            font-size: 1.3rem;
            font-weight: 900;
            background: linear-gradient(180deg, #ffffff 0%, #38bdf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.6));
            white-space: nowrap;
        }

        /* =======================================================
           BANNER FOOTER WHATSAPP & CONTACTO
        ======================================================= */
        .story-footer-cta {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 14px;
            padding: 8px 10px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        }

        .story-whatsapp-btn {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: #ffffff;
            font-weight: 800;
            font-size: 0.82rem;
            padding: 6px 10px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.45);
            letter-spacing: 0.02em;
        }

        .story-footer-text {
            font-size: 0.65rem;
            color: #f1f5f9;
            margin-top: 4px;
            font-weight: 600;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
        }

        /* Panel de Controles Laterales */
        .control-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            margin-bottom: 18px;
        }
    </style>
</head>
<body>

    <!-- Navegación Superior -->
    <nav class="navbar navbar-expand-lg barra-navegacion sticky-top">
        <div class="container-fluid px-3 px-md-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $rol === 'admin' ? 'admin.php' : 'vendedor.php' ?>">
                <span class="marca-icono" aria-hidden="true">❄️</span>
                <span class="fw-bold">Fábrica de Hielo</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a class="btn btn-outline-light btn-sm" href="<?= $rol === 'admin' ? 'admin.php' : 'vendedor.php' ?>">
                    <i class="bi bi-arrow-left me-1"></i> Volver al Panel
                </a>
                <a class="btn btn-outline-danger btn-sm" href="logout.php">Salir</a>
            </div>
        </div>
    </nav>

    <main class="container-fluid px-3 px-md-4 py-4">
        
        <div class="row g-4">
            
            <!-- Columna Izquierda: Controles y Personalizador de Story -->
            <div class="col-12 col-xl-7">
                
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <span class="badge text-bg-primary text-uppercase px-3 py-1 mb-1">Marketing Digital 9:16</span>
                        <h1 class="h3 fw-bold text-dark mb-0">Creador de Historias para Instagram</h1>
                        <p class="text-muted small mb-0">Flyers publicitarios con efecto relieve 3D, dos bolsas de hielo y precios en vivo de Firestore.</p>
                    </div>
                </div>

                <!-- 1. Selección de Estilo y Tema -->
                <div class="control-section">
                    <h2 class="h6 fw-bold text-primary mb-3">
                        <i class="bi bi-palette-fill me-2"></i> 1. Estilo Visual y Fondo Helado
                    </h2>
                    
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Plantilla de Campaña</label>
                            <select id="controlTipoPlantilla" class="form-select">
                                <option value="general" selected>🧊 Fábrica de Hielo / Lista de Precios</option>
                                <option value="oferta">🔥 Oferta Flash / Super Precio</option>
                                <option value="mayorista">🚚 Envíos Mayoristas a Comercios</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Filtro de Overlay (Fondo Personalizado)</label>
                            <select id="controlTemaFondo" class="form-select">
                                <option value="overlay-standard" selected>🌑 Overlay Oscuro 50% (Recomendado)</option>
                                <option value="overlay-dark">🌚 Overlay Oscuro 65% (Mayor Contraste)</option>
                                <option value="overlay-glaciar">❄️ Overlay Tinte Glaciar</option>
                                <option value="overlay-polar">🌌 Overlay Tinte Azul Noche</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 2. Textos Publicitarios -->
                <div class="control-section">
                    <h2 class="h6 fw-bold text-primary mb-3">
                        <i class="bi bi-fonts me-2"></i> 2. Textos y Llamados a la Acción
                    </h2>

                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Badge Superior</label>
                            <input type="text" id="controlBadge" class="form-control" value="❄️ FÁBRICA DIRECTA">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Título 3D en Relieve</label>
                            <input type="text" id="controlTitulo" class="form-control" value="HIELO EN CUBOS">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Subtítulo / Bajada</label>
                            <input type="text" id="controlSubtitulo" class="form-control" value="100% Agua Filtrada y Cristalina • Máxima Duración">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Número de WhatsApp</label>
                            <input type="text" id="controlWhatsapp" class="form-control" value="+54 9 11 4567-8900">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Texto Pie / Envíos</label>
                            <input type="text" id="controlUbicacion" class="form-control" value="Envíos a Kioscos, Bares y Eventos • Planta Central">
                        </div>
                    </div>
                </div>

                <!-- 3. Precios en Vivo desde Firestore -->
                <div class="control-section">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h6 fw-bold text-primary mb-0">
                            <i class="bi bi-currency-dollar me-2"></i> 3. Precios Actuales de Firestore
                        </h2>
                        <span class="badge text-bg-success bg-opacity-75 text-white">Base de Datos Conectada</span>
                    </div>

                    <p class="small text-muted mb-3">
                        Marcá las presentaciones de hielo que deseas mostrar en el flyer (los precios se actualizan automáticamente):
                    </p>

                    <div class="table-responsive border rounded bg-light p-2">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="small text-muted">
                                <tr>
                                    <th style="width: 45px;" class="text-center">Ver</th>
                                    <th>Presentación / Producto</th>
                                    <th>Precio Base Firestore</th>
                                    <th style="width: 130px;">Precio Story ($)</th>
                                </tr>
                            </thead>
                            <tbody id="tablaProductosStories">
                                <?php if (empty($productosFirestore)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No hay productos cargados en Firestore.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($productosFirestore as $idx => $p): ?>
                                        <tr>
                                            <td class="text-center">
                                                <input class="form-check-input check-prod-story" type="checkbox" value="<?= $p['id'] ?>" <?= $idx < 3 ? 'checked' : '' ?> data-id="<?= $p['id'] ?>">
                                            </td>
                                            <td>
                                                <strong class="small text-dark"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span class="badge text-bg-light border small text-muted ms-1"><?= ucfirst($p['presentacion']) ?></span>
                                            </td>
                                            <td class="small text-muted font-monospace">
                                                $ <?= number_format($p['precio'], 2, ',', '.') ?>
                                            </td>
                                            <td>
                                                <input type="number" step="1" class="form-control form-control-sm input-precio-story font-monospace fw-bold text-end" value="<?= (int)$p['precio'] ?>" data-id="<?= $p['id'] ?>" data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>" data-pres="<?= htmlspecialchars($p['presentacion'], ENT_QUOTES, 'UTF-8') ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Columna Derecha: Vista Previa y Botón de Descarga HD -->
            <div class="col-12 col-xl-5">
                
                <div class="phone-mockup-wrapper">
                    <div class="d-flex flex-column align-items-center">
                        
                        <!-- Marco de Celular con Story -->
                        <div class="phone-frame mb-3">
                            <div class="phone-screen">
                                <div id="storyCanvasExport" class="story-canvas overlay-standard">
                                    
                                    <!-- Escarcha de fondo -->
                                    <div class="frost-overlay"></div>

                                    <!-- Header de la Historia -->
                                    <div style="position: relative; z-index: 2;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="ice-badge-top" id="storyPreviewBadge">
                                                ❄️ FÁBRICA DIRECTA
                                            </span>
                                            <div class="text-white-50 small" style="font-size: 0.65rem; font-weight: 800; letter-spacing: 0.08em;">
                                                PREMIUM ICE
                                            </div>
                                        </div>

                                        <h2 class="story-title-3d" id="storyPreviewTitulo">
                                            HIELO EN CUBOS
                                        </h2>
                                        <p class="story-subtitle" id="storyPreviewSubtitulo">
                                            100% Agua Filtrada y Cristalina • Máxima Duración
                                        </p>
                                    </div>

                                    <!-- Gráfico Central: 2 Bolsas de Hielo Transparentes y Reales llenas de cubos -->
                                    <div class="ice-bags-stage" style="position: relative; z-index: 2;">
                                        <div class="ice-bags-svg-wrapper">
                                            <svg viewBox="0 0 400 200" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                                                <defs>
                                                    <!-- Gradientes de Bolsas Transparentes -->
                                                    <linearGradient id="bagGradLeft" x1="0%" y1="0%" x2="100%" y2="100%">
                                                        <stop offset="0%" stop-color="#ffffff" stop-opacity="0.45"/>
                                                        <stop offset="30%" stop-color="#7dd3fc" stop-opacity="0.2"/>
                                                        <stop offset="70%" stop-color="#38bdf8" stop-opacity="0.15"/>
                                                        <stop offset="100%" stop-color="#0284c7" stop-opacity="0.35"/>
                                                    </linearGradient>
                                                    <linearGradient id="bagGradRight" x1="100%" y1="0%" x2="0%" y2="100%">
                                                        <stop offset="0%" stop-color="#ffffff" stop-opacity="0.55"/>
                                                        <stop offset="40%" stop-color="#bae6fd" stop-opacity="0.25"/>
                                                        <stop offset="80%" stop-color="#0ea5e9" stop-opacity="0.2"/>
                                                        <stop offset="100%" stop-color="#0369a1" stop-opacity="0.4"/>
                                                    </linearGradient>
                                                    
                                                    <!-- Gradiente Cubos de Hielo -->
                                                    <linearGradient id="iceCubeTop" x1="0%" y1="0%" x2="100%" y2="100%">
                                                        <stop offset="0%" stop-color="#ffffff" stop-opacity="0.95"/>
                                                        <stop offset="100%" stop-color="#bae6fd" stop-opacity="0.7"/>
                                                    </linearGradient>
                                                    <linearGradient id="iceCubeLeft" x1="0%" y1="0%" x2="100%" y2="100%">
                                                        <stop offset="0%" stop-color="#7dd3fc" stop-opacity="0.8"/>
                                                        <stop offset="100%" stop-color="#0284c7" stop-opacity="0.9"/>
                                                    </linearGradient>
                                                    <linearGradient id="iceCubeRight" x1="0%" y1="0%" x2="100%" y2="100%">
                                                        <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.85"/>
                                                        <stop offset="100%" stop-color="#0369a1" stop-opacity="0.95"/>
                                                    </linearGradient>
                                                    
                                                    <!-- Filtro de Resplandor Frío -->
                                                    <filter id="iceGlow" x="-20%" y="-20%" width="140%" height="140%">
                                                        <feGaussianBlur stdDeviation="3" result="blur"/>
                                                        <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                                                    </filter>
                                                </defs>

                                                <!-- Sombra / Resplandor Base en el suelo -->
                                                <ellipse cx="200" cy="185" rx="160" ry="12" fill="#000000" opacity="0.45" />
                                                <ellipse cx="200" cy="183" rx="130" ry="8" fill="#38bdf8" opacity="0.25" />

                                                <!-- ==========================================
                                                     BOLSA IZQUIERDA (Bolsa de Hielo 1)
                                                =========================================== -->
                                                <g transform="translate(60, 15) rotate(-6, 80, 90)">
                                                    <!-- Cuerpo de la Bolsa -->
                                                    <path d="M 30,35 C 45,30 115,30 130,35 C 145,55 150,135 140,165 C 130,175 30,175 20,165 C 10,135 15,55 30,35 Z" 
                                                          fill="url(#bagGradLeft)" stroke="rgba(255,255,255,0.7)" stroke-width="2" />

                                                    <!-- Cubos de Hielo dentro de la Bolsa 1 -->
                                                    <!-- Fila Inferior de Cubos -->
                                                    <g transform="translate(32, 115) scale(0.65)">
                                                        <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                        <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                        <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                    </g>
                                                    <g transform="translate(62, 118) scale(0.68)">
                                                        <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                        <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                        <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                    </g>
                                                    <g transform="translate(92, 112) scale(0.65)">
                                                        <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                        <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                        <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                    </g>
                                                    <!-- Fila Media de Cubos -->
                                                    <g transform="translate(42, 85) scale(0.7)">
                                                        <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                        <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                        <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                    </g>
                                                    <g transform="translate(75, 82) scale(0.72)">
                                                        <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                        <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                        <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                    </g>
                                                    <!-- Fila Superior de Cubos -->
                                                    <g transform="translate(58, 52) scale(0.68)">
                                                        <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                        <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                        <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                    </g>

                                                    <!-- Brillo y Pliegues de la Bolsa 1 -->
                                                    <path d="M 28,45 C 50,70 40,140 32,158" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" opacity="0.6" fill="none"/>
                                                    <path d="M 125,50 C 130,90 120,135 128,155" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" opacity="0.5" fill="none"/>
                                                    
                                                    <!-- Fruncido / Cierre Superior de la Bolsa -->
                                                    <path d="M 68,32 C 60,15 55,5 80,4 C 105,5 100,15 92,32 Z" fill="url(#bagGradLeft)" stroke="#ffffff" stroke-width="1.5"/>
                                                    <ellipse cx="80" cy="30" rx="14" ry="4" fill="#0284c7" stroke="#ffffff" stroke-width="1.5"/>

                                                    <!-- Etiqueta Helada en la Bolsa -->
                                                    <rect x="45" y="100" width="70" height="26" rx="5" fill="#0369a1" fill-opacity="0.85" stroke="#bae6fd" stroke-width="1"/>
                                                    <text x="80" y="117" font-family="'Outfit', sans-serif" font-size="11" font-weight="900" fill="#ffffff" text-anchor="middle" letter-spacing="1">HIELO</text>
                                                </g>

                                                <!-- ==========================================
                                                     BOLSA DERECHA (Bolsa de Hielo 2)
                                                =========================================== -->
                                                <g transform="translate(180, 10) rotate(5, 80, 95)">
                                                    <!-- Cuerpo de la Bolsa 2 -->
                                                    <path d="M 30,35 C 45,28 120,28 135,35 C 150,55 155,140 142,170 C 130,178 30,178 18,170 C 8,140 15,55 30,35 Z" 
                                                          fill="url(#bagGradRight)" stroke="rgba(255,255,255,0.85)" stroke-width="2.2" />

                                                    <!-- Cubos de Hielo dentro de la Bolsa 2 -->
                                                    <!-- Fila Inferior -->
                                                    <g transform="translate(30, 118) scale(0.7)">
                                                        <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                        <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                        <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                    </g>
                                                    <g transform="translate(62, 122) scale(0.72)">
                                                        <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                        <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                        <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                    </g>
                                                    <g transform="translate(95, 115) scale(0.68)">
                                                        <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                        <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                        <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                    </g>
                                                    <!-- Fila Media -->
                                                    <g transform="translate(45, 86) scale(0.74)">
                                                        <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                        <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                        <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                    </g>
                                                    <g transform="translate(78, 84) scale(0.74)">
                                                        <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                        <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                        <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                    </g>
                                                    <!-- Fila Superior -->
                                                    <g transform="translate(60, 52) scale(0.72)">
                                                        <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                        <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                        <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                    </g>

                                                    <!-- Brillo y Reflejos Plásticos Bolsa 2 -->
                                                    <path d="M 28,45 C 55,75 42,145 32,165" stroke="#ffffff" stroke-width="3" stroke-linecap="round" opacity="0.75" fill="none"/>
                                                    <path d="M 132,50 C 135,95 125,140 132,160" stroke="#ffffff" stroke-width="2" stroke-linecap="round" opacity="0.6" fill="none"/>
                                                    
                                                    <!-- Fruncido / Cierre Superior Bolsa 2 -->
                                                    <path d="M 70,30 C 62,12 58,2 84,2 C 110,2 105,12 96,30 Z" fill="url(#bagGradRight)" stroke="#ffffff" stroke-width="1.5"/>
                                                    <ellipse cx="83" cy="28" rx="15" ry="4" fill="#0284c7" stroke="#ffffff" stroke-width="1.5"/>

                                                    <!-- Etiqueta Helada en la Bolsa 2 -->
                                                    <rect x="48" y="100" width="72" height="28" rx="5" fill="#0284c7" fill-opacity="0.9" stroke="#ffffff" stroke-width="1.2"/>
                                                    <text x="84" y="118" font-family="'Outfit', sans-serif" font-size="11.5" font-weight="900" fill="#ffffff" text-anchor="middle" letter-spacing="1">ROLITOS</text>
                                                </g>

                                                <!-- Cubos de Hielo Flotando Sueltos en la Base -->
                                                <g transform="translate(185, 155) scale(0.55)">
                                                    <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                    <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                    <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                </g>
                                                <g transform="translate(45, 155) rotate(15) scale(0.5)">
                                                    <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                    <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                    <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                </g>
                                                <g transform="translate(325, 150) rotate(-20) scale(0.52)">
                                                    <polygon points="20,0 40,10 20,20 0,10" fill="url(#iceCubeTop)"/>
                                                    <polygon points="0,10 20,20 20,40 0,30" fill="url(#iceCubeLeft)"/>
                                                    <polygon points="20,20 40,10 40,30 20,40" fill="url(#iceCubeRight)"/>
                                                </g>
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- Recuadro Moderno de Precios Reales (Firestore) -->
                                    <div class="story-pricing-box" style="position: relative; z-index: 2;">
                                        <div class="story-pricing-header">
                                            <span class="story-pricing-title">
                                                <i class="bi bi-tag-fill"></i> Precios Actualizados
                                            </span>
                                            <span class="badge text-bg-info bg-opacity-25 text-info border border-info border-opacity-50" style="font-size: 0.58rem; font-weight: 700;">
                                                DIRECTO DE FÁBRICA
                                            </span>
                                        </div>

                                        <!-- Contenedor Dinámico de Filas de Precios -->
                                        <div class="story-items-list" id="storyPreviewProductosContainer">
                                            <!-- Render dinámico desde JS -->
                                        </div>
                                    </div>

                                    <!-- Footer con WhatsApp y Contacto -->
                                    <div class="story-footer-cta" style="position: relative; z-index: 2;">
                                        <div class="story-whatsapp-btn">
                                            <i class="bi bi-whatsapp fs-6"></i>
                                            <span id="storyPreviewWhatsapp">PEDIDOS: +54 9 11 4567-8900</span>
                                        </div>
                                        <div class="story-footer-text" id="storyPreviewUbicacion">
                                            Envíos a Kioscos, Bares y Eventos • Planta Central
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="d-flex flex-column gap-2 w-100" style="max-width: 380px;">
                            <button type="button" class="btn btn-success btn-lg fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnDescargarHistoriaPng">
                                <i class="bi bi-cloud-arrow-down-fill fs-5"></i> Descargar Historia PNG HD
                            </button>
                            <button type="button" class="btn btn-outline-dark fw-semibold" id="btnCopiarCaption">
                                <i class="bi bi-card-text me-1"></i> Copiar Texto / Caption para Instagram
                            </button>
                        </div>

                    </div>
                </div>

            </div>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const formatoMoneda = new Intl.NumberFormat("es-AR", { style: "currency", currency: "ARS", maximumFractionDigits: 0 });

        // Elementos de Control
        const selectTema = document.getElementById("controlTemaFondo");
        const inputBadge = document.getElementById("controlBadge");
        const inputTitulo = document.getElementById("controlTitulo");
        const inputSubtitulo = document.getElementById("controlSubtitulo");
        const inputWhatsapp = document.getElementById("controlWhatsapp");
        const inputUbicacion = document.getElementById("controlUbicacion");
        const selectTipoPlantilla = document.getElementById("controlTipoPlantilla");

        // Elementos de la Vista Previa
        const canvasStory = document.getElementById("storyCanvasExport");
        const previewBadge = document.getElementById("storyPreviewBadge");
        const previewTitulo = document.getElementById("storyPreviewTitulo");
        const previewSubtitulo = document.getElementById("storyPreviewSubtitulo");
        const previewWhatsapp = document.getElementById("storyPreviewWhatsapp");
        const previewUbicacion = document.getElementById("storyPreviewUbicacion");
        const previewContainerProds = document.getElementById("storyPreviewProductosContainer");

        function actualizarStory() {
            // 1. Tema de fondo
            canvasStory.className = `story-canvas ${selectTema.value}`;

            // 2. Textos
            previewBadge.textContent = inputBadge.value || "❄️ FÁBRICA DIRECTA";
            previewTitulo.textContent = inputTitulo.value || "HIELO EN CUBOS";
            previewSubtitulo.textContent = inputSubtitulo.value || "100% Agua Filtrada y Cristalina • Máxima Duración";
            previewWhatsapp.textContent = `PEDIDOS: ${inputWhatsapp.value || '+54 9 11 0000-0000'}`;
            previewUbicacion.textContent = inputUbicacion.value || "Envíos a Kioscos, Bares y Eventos • Planta Central";

            // 3. Renderizar Productos y Precios
            const checkboxes = document.querySelectorAll(".check-prod-story:checked");
            previewContainerProds.innerHTML = "";

            const plantilla = selectTipoPlantilla.value;

            if (checkboxes.length === 0) {
                previewContainerProds.innerHTML = `
                    <div class="p-2 text-center bg-white bg-opacity-10 rounded-2 text-white-50" style="font-size: 0.72rem;">
                        Seleccioná productos de la tabla para mostrarlos aquí.
                    </div>
                `;
                return;
            }

            // Mostrar hasta un máximo de 3-4 productos para ajuste visual perfecto
            checkboxes.forEach((chk, index) => {
                if (index >= 4) return;
                const id = chk.value;
                const inputPrecio = document.querySelector(`.input-precio-story[data-id="${id}"]`);
                const nombre = inputPrecio ? inputPrecio.dataset.nombre : "Bolsa de Hielo";
                const pres = inputPrecio ? inputPrecio.dataset.pres : "unidad";
                const precioNum = inputPrecio ? parseFloat(inputPrecio.value) || 0 : 0;

                const row = document.createElement("div");
                row.className = "story-price-row";

                if (plantilla === "oferta" && index === 0) {
                    row.style.background = "linear-gradient(90deg, rgba(245, 158, 11, 0.3) 0%, rgba(255, 255, 255, 0.15) 100%)";
                    row.style.borderColor = "#f59e0b";
                    row.innerHTML = `
                        <div class="story-price-info">
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge bg-warning text-dark p-1" style="font-size: 0.55rem; font-weight: 800;">OFERTA</span>
                                <span class="story-prod-name">${nombre}</span>
                            </div>
                            <span class="story-prod-desc">Bolsa cristalina pura • ¡Súper Promo!</span>
                        </div>
                        <div class="story-prod-price text-warning">${formatoMoneda.format(precioNum)}</div>
                    `;
                } else {
                    let detallePres = "Bolsa individual";
                    if (pres === "caja") detallePres = "Caja cerrada";
                    else if (pres === "bulto") detallePres = "Pack / Bulto cerrado";

                    row.innerHTML = `
                        <div class="story-price-info">
                            <span class="story-prod-name">${nombre}</span>
                            <span class="story-prod-desc">${detallePres}</span>
                        </div>
                        <div class="story-prod-price">${formatoMoneda.format(precioNum)}</div>
                    `;
                }

                previewContainerProds.appendChild(row);
            });
        }

        // Listeners en tiempo real
        [selectTema, inputBadge, inputTitulo, inputSubtitulo, inputWhatsapp, inputUbicacion, selectTipoPlantilla].forEach(el => {
            if (el) {
                el.addEventListener("input", actualizarStory);
                el.addEventListener("change", actualizarStory);
            }
        });

        document.querySelectorAll(".check-prod-story, .input-precio-story").forEach(el => {
            el.addEventListener("input", actualizarStory);
            el.addEventListener("change", actualizarStory);
        });

        // Cambiar plantilla con valores predeterminados de marketing
        selectTipoPlantilla.addEventListener("change", () => {
            const p = selectTipoPlantilla.value;
            if (p === "oferta") {
                inputBadge.value = "🔥 ¡SUPER OFERTA FLASH!";
                inputTitulo.value = "PROMO DEL DÍA";
                inputSubtitulo.value = "Precios especiales en bolsas de hielo por tiempo limitado";
            } else if (p === "mayorista") {
                inputBadge.value = "🚚 REPARTO MAYORISTA";
                inputTitulo.value = "PROVEEDOR DE HIELO";
                inputSubtitulo.value = "Abastecimiento constante a Kioscos, Bares y Gastronomía";
            } else {
                inputBadge.value = "❄️ FÁBRICA DIRECTA";
                inputTitulo.value = "HIELO EN CUBOS";
                inputSubtitulo.value = "100% Agua Filtrada y Cristalina • Máxima Duración";
            }
            actualizarStory();
        });

        // 4. Descargar Historia en Formato PNG Alta Resolución (1080x1920)
        document.getElementById("btnDescargarHistoriaPng").addEventListener("click", async () => {
            const btn = document.getElementById("btnDescargarHistoriaPng");
            const textoOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Generando PNG HD...`;

            try {
                const elemento = document.getElementById("storyCanvasExport");
                
                // Renderizar con html2canvas en ultra alta definición (escala 3x para 1080x1920 nítido)
                const canvas = await html2canvas(elemento, {
                    scale: 3,
                    useCORS: true,
                    logging: false,
                    backgroundColor: null
                });

                const link = document.createElement("a");
                const fechaHoy = new Date().toISOString().slice(0, 10);
                link.download = `Historia_Instagram_Hielo_${fechaHoy}.png`;
                link.href = canvas.toDataURL("image/png");
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

            } catch (err) {
                console.error(err);
                alert("Error al exportar la historia: " + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;
            }
        });

        // 5. Copiar Caption para Instagram
        document.getElementById("btnCopiarCaption").addEventListener("click", async () => {
            let listaPrecios = "";
            const checkboxes = document.querySelectorAll(".check-prod-story:checked");
            checkboxes.forEach(chk => {
                const id = chk.value;
                const inputPrecio = document.querySelector(`.input-precio-story[data-id="${id}"]`);
                if (inputPrecio) {
                    listaPrecios += `🧊 *${inputPrecio.dataset.nombre}:* $ ${inputPrecio.value}\n`;
                }
            });

            const caption = 
                `❄️ *${inputTitulo.value.toUpperCase()} - PRECIOS DE FÁBRICA* ❄️\n\n` +
                `${inputSubtitulo.value}\n\n` +
                `📋 *Precios vigentes:*\n` +
                `${listaPrecios}\n` +
                `🚚 *Reparto y Pedidos:* ${inputWhatsapp.value}\n` +
                `📍 ${inputUbicacion.value}\n\n` +
                `#Hielo #FabricaDeHielo #Rolitos #BolsasDeHielo #Bebidas #Eventos #Gastronomia #Verano`;

            try {
                await navigator.clipboard.writeText(caption);
                alert("¡Texto y pie de foto copiado al portapapeles listo para pegar en Instagram!");
            } catch {
                prompt("Copia el siguiente texto:", caption);
            }
        });

        // Inicializar al cargar
        document.addEventListener("DOMContentLoaded", actualizarStory);
    </script>
</body>
</html>
