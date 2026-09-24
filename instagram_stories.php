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
            background: #09121f;
            border: 1px solid #1e3a5f;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            box-shadow: none;
            color: #ffffff;
        }

        /* =======================================================
           TÍTULO PRINCIPAL 3D CON RELIEVE Y SOMBRAS PURAS
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
            color: #ffffff;
            text-shadow: 
                0 2px 0 #0284c7,
                0 3px 0 #0369a1,
                0 5px 12px rgba(0, 0, 0, 0.9),
                0 0 20px rgba(56, 189, 248, 0.85);
        }

        .story-subtitle {
            font-size: 0.78rem;
            text-align: center;
            color: #e0f2fe;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 4px;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.9), 0 0 10px rgba(0, 0, 0, 0.7);
        }

        /* Espacio Central para lucir la foto */
        .ice-bags-stage {
            position: relative;
            width: 100%;
            height: 165px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* =======================================================
           RECUADRO DE PRECIOS REALES FIRESTORE (FONDO OSCURO SÓLIDO)
        ======================================================= */
        .story-pricing-box {
            background: #09121f;
            border: 1px solid #1e3a5f;
            border-radius: 16px;
            padding: 9px 11px;
            box-shadow: none;
            margin-bottom: 8px;
        }

        .story-pricing-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 4px;
            margin-bottom: 4px;
            border-bottom: 1px solid #1e293b;
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
            background: #0f1e33;
            border: 1px solid #1e3a5f;
            border-radius: 10px;
            padding: 5px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: none;
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
            color: #38bdf8;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.8), 0 0 12px rgba(56, 189, 248, 0.5);
            white-space: nowrap;
        }

        /* =======================================================
           BANNER FOOTER WHATSAPP & CONTACTO (FONDO OSCURO SÓLIDO)
        ======================================================= */
        .story-footer-cta {
            background: #09121f;
            border: 1px solid #1e3a5f;
            border-radius: 14px;
            padding: 8px 10px;
            text-align: center;
            box-shadow: none;
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
                                <div id="storyCanvasExport" class="story-canvas" style="position: relative; overflow: hidden; background: #031526;">
                                    
                                    <!-- Imagen de Fondo Real Precargada con CrossOrigin -->
                                    <img id="storyRealBgImg" src="assets/FONDO%20HISTORIA.jpg" crossOrigin="anonymous" alt="Fondo Hielo" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; pointer-events: none;">
                                    
                                    <!-- Capa de Overlay Oscuro para Alto Contraste -->
                                    <div id="storyOverlayLayer" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.42); z-index: 1; pointer-events: none;"></div>

                                    <!-- Header de la Historia -->
                                    <div style="position: relative; z-index: 3;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="ice-badge-top" id="storyPreviewBadge">
                                                ❄️ FÁBRICA DIRECTA
                                            </span>
                                            <div class="text-white-50 small" style="font-size: 0.65rem; font-weight: 800; letter-spacing: 0.08em; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">
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

                                    <!-- Espacio Central: Permite lucir las 2 Bolsas de Hielo Reales de la imagen de fondo -->
                                    <div class="ice-bags-stage" style="position: relative; z-index: 3; height: 165px; display: flex; align-items: center; justify-content: center;">
                                        <!-- Área despejada y nítida para la fotografía de fondo -->
                                    </div>

                                    <!-- Recuadro Moderno de Precios Reales (Firestore) -->
                                    <div class="story-pricing-box" style="position: relative; z-index: 3;">
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
                                    <div class="story-footer-cta" style="position: relative; z-index: 3;">
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
                            <button type="button" class="btn btn-success btn-lg fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnCompartirWhatsapp">
                                <i class="bi bi-whatsapp fs-5"></i> Compartir por WhatsApp
                            </button>
                            <button type="button" class="btn btn-primary fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnDescargarHistoriaPng">
                                <i class="bi bi-cloud-arrow-down-fill fs-5"></i> Descargar Imagen PNG HD
                            </button>
                            <button type="button" class="btn btn-outline-dark fw-semibold d-flex align-items-center justify-content-center gap-2" id="btnCopiarCaption">
                                <i class="bi bi-card-text me-1"></i> Copiar Texto / Caption
                            </button>
                        </div>

                    </div>
                </div>

            </div>

        </div>

        <!-- Modal de Vista Previa y Respaldo para iPhone / Dispositivos Móviles -->
        <div class="modal fade" id="modalPreviewIos" tabindex="-1" aria-labelledby="modalPreviewIosLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header bg-dark text-white border-0 py-3">
                        <h5 class="modal-title fs-6 fw-bold" id="modalPreviewIosLabel">
                            <i class="bi bi-phone-fill text-info me-2"></i> Imagen para iPhone / Celular
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body text-center p-3">
                        <div class="alert alert-primary py-2 px-3 small d-flex align-items-center justify-content-center gap-2 mb-3 text-start">
                            <i class="bi bi-hand-index-thumb fs-4 text-primary"></i>
                            <div>
                                <strong>En iPhone:</strong> Mantén presionada la imagen y selecciona <strong>"Guardar en Fotos"</strong> o <strong>"Compartir"</strong>.
                            </div>
                        </div>
                        <div class="position-relative d-inline-block shadow-sm rounded-3 overflow-hidden bg-dark" style="max-height: 480px;">
                            <img id="imgIosPreview" src="" alt="Historia Instagram Hielo" class="img-fluid rounded-3" style="max-height: 470px; object-fit: contain;">
                        </div>
                    </div>
                    <div class="modal-footer border-0 d-flex flex-column gap-2 pt-0 pb-3">
                        <button type="button" class="btn btn-success w-100 fw-bold py-2" id="btnModalShareWa">
                            <i class="bi bi-whatsapp me-1"></i> Abrir Chat de WhatsApp
                        </button>
                        <button type="button" class="btn btn-light border btn-sm w-100" data-bs-dismiss="modal">
                            Cerrar
                        </button>
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
            // 1. Fondo e imagen obligatoria
            if (canvasStory) {
                canvasStory.style.backgroundImage = "url('assets/FONDO HISTORIA.jpg')";
                canvasStory.style.backgroundSize = "cover";
                canvasStory.style.backgroundPosition = "center center";
                canvasStory.style.backgroundRepeat = "no-repeat";
                canvasStory.style.backgroundBlendMode = "overlay";
            }
            const overlayClass = selectTema ? selectTema.value : "overlay-standard";
            canvasStory.className = `story-canvas ${overlayClass}`;

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

        // =======================================================
        // UTILIDADES DE RENDERIZADO Y EXPORTACIÓN HD
        // =======================================================
        function obtenerCaptionTexto() {
            let listaPrecios = "";
            const checkboxes = document.querySelectorAll(".check-prod-story:checked");
            checkboxes.forEach(chk => {
                const id = chk.value;
                const inputPrecio = document.querySelector(`.input-precio-story[data-id="${id}"]`);
                if (inputPrecio) {
                    listaPrecios += `🧊 *${inputPrecio.dataset.nombre}:* $ ${inputPrecio.value}\n`;
                }
            });

            return `❄️ *${(inputTitulo.value || "HIELO EN CUBOS").toUpperCase()} - PRECIOS DE FÁBRICA* ❄️\n\n` +
                   `${inputSubtitulo.value || ""}\n\n` +
                   `📋 *Precios vigentes:*\n` +
                   `${listaPrecios}\n` +
                   `🚚 *Reparto y Pedidos:* ${inputWhatsapp.value || ""}\n` +
                   `📍 ${inputUbicacion.value || ""}\n\n` +
                   `#Hielo #FabricaDeHielo #Rolitos #BolsasDeHielo #Bebidas #Eventos #Gastronomia #Verano`;
        }

        function precargarImagenFondo(src) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = "anonymous";
                img.onload = () => resolve(img);
                img.onerror = () => {
                    const img2 = new Image();
                    img2.crossOrigin = "anonymous";
                    img2.onload = () => resolve(img2);
                    img2.onerror = err => reject(err);
                    img2.src = encodeURI(src);
                };
                img.src = src;
            });
        }

        async function renderizarCanvasStory() {
            const elemento = document.getElementById("storyCanvasExport");

            // 1. Precargar obligatoriamente la imagen de fondo con new Image() y crossOrigin
            let imgFondo = null;
            try {
                imgFondo = await precargarImagenFondo("assets/FONDO HISTORIA.jpg");
            } catch (e1) {
                try {
                    imgFondo = await precargarImagenFondo("assets/FONDO%20HISTORIA.jpg");
                } catch (e2) {
                    console.warn("Aviso al precargar fondo:", e2);
                }
            }

            // 2. Renderizar elementos con html2canvas configurado para evitar artefactos
            const canvasRender = await html2canvas(elemento, {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                logging: false,
                backgroundColor: null,
                onclone: (clonedDoc) => {
                    const clonedExport = clonedDoc.getElementById("storyCanvasExport");
                    if (clonedExport) {
                        // Ocultar imagen de fondo y capa de overlay en el clon (se componen directamente en el canvas 2D)
                        const clonedBg = clonedDoc.getElementById("storyRealBgImg");
                        if (clonedBg) clonedBg.style.display = "none";

                        const clonedOverlay = clonedDoc.getElementById("storyOverlayLayer");
                        if (clonedOverlay) clonedOverlay.style.display = "none";

                        // Limpiar filtros, sombras y propiedades que generan halos o cajas blancas en html2canvas
                        clonedExport.querySelectorAll("*").forEach(node => {
                            node.style.backdropFilter = "none";
                            node.style.webkitBackdropFilter = "none";
                            node.style.boxShadow = "none";
                            node.style.filter = "none";
                            node.style.webkitBackgroundClip = "unset";
                            node.style.backgroundClip = "unset";
                            node.style.webkitTextFillColor = "unset";
                        });

                        // Forzar fondo plano sólido y borde limpio en el contenedor de precios
                        const clonedPricingBox = clonedDoc.querySelector(".story-pricing-box");
                        if (clonedPricingBox) {
                            clonedPricingBox.style.backgroundColor = "#09121f";
                            clonedPricingBox.style.borderColor = "#1e3a5f";
                            clonedPricingBox.style.boxShadow = "none";
                        }

                        // Forzar fondo plano sólido en cada fila de precio
                        clonedDoc.querySelectorAll(".story-price-row").forEach(r => {
                            r.style.backgroundColor = "#0f1e33";
                            r.style.borderColor = "#1e3a5f";
                            r.style.boxShadow = "none";
                        });

                        // Forzar fondo sólido en footer CTA y badge
                        const clonedFooter = clonedDoc.querySelector(".story-footer-cta");
                        if (clonedFooter) {
                            clonedFooter.style.backgroundColor = "#09121f";
                            clonedFooter.style.borderColor = "#1e3a5f";
                            clonedFooter.style.boxShadow = "none";
                        }

                        const clonedBadge = clonedDoc.getElementById("storyPreviewBadge");
                        if (clonedBadge) {
                            clonedBadge.style.backgroundColor = "#09121f";
                            clonedBadge.style.borderColor = "#1e3a5f";
                            clonedBadge.style.boxShadow = "none";
                        }

                        // Título 3D ultra limpio con sombras puras
                        const clonedTitle = clonedDoc.getElementById("storyPreviewTitulo");
                        if (clonedTitle) {
                            clonedTitle.style.color = "#ffffff";
                            clonedTitle.style.background = "none";
                            clonedTitle.style.textShadow = "0 2px 0 #0284c7, 0 3px 0 #0369a1, 0 5px 12px rgba(0,0,0,0.9), 0 0 16px rgba(56, 189, 248, 0.85)";
                        }

                        // Precios celestes brillantes sin cajas ni gradientes recortados
                        clonedExport.querySelectorAll(".story-prod-price").forEach(el => {
                            el.style.color = "#38bdf8";
                            el.style.background = "none";
                            el.style.textShadow = "0 2px 6px rgba(0,0,0,0.85), 0 0 10px rgba(56, 189, 248, 0.4)";
                        });
                    }
                }
            });

            // 3. Crear lienzo final en alta definición y componer la imagen de fondo real con exactitud
            const finalCanvas = document.createElement("canvas");
            finalCanvas.width = canvasRender.width;
            finalCanvas.height = canvasRender.height;
            const ctx = finalCanvas.getContext("2d");

            if (imgFondo) {
                // Dibujar la imagen de fondo en toda la proporción vertical 9:16
                ctx.drawImage(imgFondo, 0, 0, finalCanvas.width, finalCanvas.height);
                
                // Aplicar overlay oscuro uniforme
                const overlayEl = document.getElementById("storyOverlayLayer");
                ctx.fillStyle = (overlayEl && overlayEl.style.backgroundColor) ? overlayEl.style.backgroundColor : "rgba(0, 0, 0, 0.42)";
                ctx.fillRect(0, 0, finalCanvas.width, finalCanvas.height);
            }

            // Dibujar encima con transparencia perfecta todos los textos, badges y precios
            ctx.drawImage(canvasRender, 0, 0);

            return finalCanvas;
        }

        function canvasToBlobAsync(canvas) {
            return new Promise((resolve, reject) => {
                canvas.toBlob(blob => {
                    if (blob) resolve(blob);
                    else reject(new Error("No se pudo procesar la imagen generada."));
                }, "image/png");
            });
        }

        function mostrarModalIosFallback(dataUrl, caption) {
            const modalEl = document.getElementById("modalPreviewIos");
            const imgEl = document.getElementById("imgIosPreview");
            const btnWaModal = document.getElementById("btnModalShareWa");

            if (imgEl) imgEl.src = dataUrl;
            if (btnWaModal) {
                btnWaModal.onclick = () => {
                    const urlWa = `https://api.whatsapp.com/send?text=${encodeURIComponent(caption)}`;
                    window.open(urlWa, "_blank");
                };
            }

            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }

        // =======================================================
        // 4. BOTÓN: COMPARTIR POR WHATSAPP (Soporte Nativo iOS / Android)
        // =======================================================
        document.getElementById("btnCompartirWhatsapp").addEventListener("click", async () => {
            const btn = document.getElementById("btnCompartirWhatsapp");
            const textoOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Preparando para WhatsApp...`;

            const caption = obtenerCaptionTexto();

            try {
                const canvas = await renderizarCanvasStory();
                const blob = await canvasToBlobAsync(canvas);
                const dataUrl = canvas.toDataURL("image/png");
                const file = new File([blob], `Historia_Hielo_${Date.now()}.png`, { type: "image/png" });

                // 1. Intentar compartir con la API Nativa de Web Share (iOS Safari 15+ y navegadores móviles)
                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    try {
                        await navigator.share({
                            files: [file],
                            title: inputTitulo.value || "Historia Fábrica de Hielo",
                            text: caption
                        });
                        return; // Compartido exitosamente mediante el menú nativo
                    } catch (shareErr) {
                        if (shareErr.name === "AbortError") {
                            return; // El usuario cerró el modal nativo voluntariamente
                        }
                        console.warn("Fallo Web Share con archivo, ejecutando fallback:", shareErr);
                    }
                }

                // 2. Fallback: Mostrar modal con imagen para guardar/compartir en iOS y abrir WhatsApp
                mostrarModalIosFallback(dataUrl, caption);
                
                const urlWa = `https://api.whatsapp.com/send?text=${encodeURIComponent(caption)}`;
                window.open(urlWa, "_blank");

            } catch (err) {
                console.error(err);
                alert("Error al procesar la imagen para compartir: " + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;
            }
        });

        // =======================================================
        // 5. BOTÓN: DESCARGAR HISTORIA PNG HD (Con Adaptación para iPhone)
        // =======================================================
        document.getElementById("btnDescargarHistoriaPng").addEventListener("click", async () => {
            const btn = document.getElementById("btnDescargarHistoriaPng");
            const textoOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Generando PNG HD...`;

            try {
                const canvas = await renderizarCanvasStory();
                const dataUrl = canvas.toDataURL("image/png");
                const fechaHoy = new Date().toISOString().slice(0, 10);
                const fileName = `Historia_Instagram_Hielo_${fechaHoy}.png`;

                // Detección de dispositivos iOS (iPhone / iPad)
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);

                if (isIOS) {
                    const blob = await canvasToBlobAsync(canvas);
                    const file = new File([blob], fileName, { type: "image/png" });

                    // En iOS, navigator.share permite seleccionar directamente "Guardar Imagen" en Fotos
                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        try {
                            await navigator.share({
                                files: [file],
                                title: "Historia de Hielo"
                            });
                            return;
                        } catch (shareErr) {
                            if (shareErr.name === "AbortError") return;
                        }
                    }

                    // Si no está disponible o falla, desplegar el modal interactivo para iOS
                    mostrarModalIosFallback(dataUrl, obtenerCaptionTexto());
                } else {
                    // Descarga directa tradicional para PC y Android
                    const link = document.createElement("a");
                    link.download = fileName;
                    link.href = dataUrl;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }

            } catch (err) {
                console.error(err);
                alert("Error al exportar la historia: " + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;
            }
        });

        // =======================================================
        // 6. BOTÓN: COPIAR CAPTION PARA INSTAGRAM
        // =======================================================
        document.getElementById("btnCopiarCaption").addEventListener("click", async () => {
            const caption = obtenerCaptionTexto();

            try {
                await navigator.clipboard.writeText(caption);
                alert("¡Texto y pie de foto copiado al portapapeles listo para pegar en Instagram o WhatsApp!");
            } catch {
                prompt("Copia el siguiente texto:", caption);
            }
        });

        // Inicializar al cargar
        document.addEventListener("DOMContentLoaded", actualizarStory);
    </script>
</body>
</html>
