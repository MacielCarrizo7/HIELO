<?php
/**
 * Módulo Generador de Historias para Instagram (Formato Vertical 9:16)
 * Consulta precios y presentaciones de hielo en tiempo real desde Google Firestore.
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    
    <!-- html2canvas para renderizar y exportar en PNG HD -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <link rel="stylesheet" href="assets/estilos.css">
    <style>
        :root {
            --story-font-heading: 'Outfit', sans-serif;
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
            width: 375px;
            height: 667px;
            background: #000000;
            border-radius: 40px;
            padding: 12px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35), 0 0 0 1px rgba(255,255,255,0.1);
            position: relative;
            user-select: none;
        }

        .phone-screen {
            width: 100%;
            height: 100%;
            border-radius: 30px;
            overflow: hidden;
            position: relative;
            background: #0f172a;
        }

        /* Lienzo de la Historia (Diseño 9:16) */
        .story-canvas {
            width: 100%;
            height: 100%;
            padding: 24px 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            color: #ffffff;
            box-sizing: border-box;
            background-size: cover;
            background-position: center;
            transition: background 0.3s ease;
        }

        /* Temas de Fondo */
        .theme-glaciar {
            background: linear-gradient(165deg, #0284c7 0%, #0369a1 40%, #0f172a 100%);
        }
        .theme-polar-night {
            background: linear-gradient(165deg, #0f172a 0%, #1e293b 50%, #082f49 100%);
        }
        .theme-cyber-ice {
            background: linear-gradient(165deg, #0284c7 0%, #2563eb 40%, #4338ca 100%);
        }
        .theme-arctic-frost {
            background: linear-gradient(165deg, #0284c7 0%, #0ea5e9 30%, #e0f2fe 100%);
        }

        /* Efectos decorativos de hielo */
        .ice-badge-top {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .story-title {
            font-family: var(--story-font-heading);
            font-size: 1.65rem;
            font-weight: 900;
            line-height: 1.1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            letter-spacing: -0.02em;
        }

        .story-subtitle {
            font-size: 0.82rem;
            opacity: 0.9;
            font-weight: 500;
            line-height: 1.35;
        }

        /* Tarjeta Glassmorphism de Precios */
        .story-cards-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 10px 0;
            max-height: 340px;
            overflow-y: hidden;
        }

        .story-item-card {
            background: rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 14px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .story-item-name {
            font-family: var(--story-font-heading);
            font-size: 0.95rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .story-item-pres {
            font-size: 0.72rem;
            opacity: 0.8;
        }

        .story-item-price {
            font-family: var(--story-font-heading);
            font-size: 1.35rem;
            font-weight: 900;
            background: linear-gradient(180deg, #ffffff 0%, #bae6fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 8px rgba(0,0,0,0.25);
            white-space: nowrap;
        }

        /* Banner Inferior WhatsApp */
        .story-footer-cta {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 18px;
            padding: 10px 14px;
            text-align: center;
        }

        .story-whatsapp-btn {
            background: #22c55e;
            color: #ffffff;
            font-weight: 800;
            font-size: 0.88rem;
            padding: 8px 12px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.4);
        }

        .story-footer-text {
            font-size: 0.7rem;
            opacity: 0.85;
            margin-top: 5px;
        }

        /* Control Cards */
        .control-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            margin-bottom: 20px;
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
                        <span class="badge text-bg-primary text-uppercase px-3 py-1 mb-1">Marketing & Redes Sociales</span>
                        <h1 class="h3 fw-bold text-dark mb-0">Generador de Historias para Instagram</h1>
                        <p class="text-muted small mb-0">Crea gráficas en formato 9:16 con los precios reales sincronizados de Firestore.</p>
                    </div>
                </div>

                <!-- 1. Selección de Plantilla y Tema -->
                <div class="control-section">
                    <h2 class="h6 fw-bold text-primary mb-3">
                        <i class="bi bi-palette-fill me-2"></i> 1. Plantilla y Estilo Visual
                    </h2>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Tipo de Publicación</label>
                            <select id="controlTipoPlantilla" class="form-select">
                                <option value="lista" selected>📋 Lista de Precios de Fábrica</option>
                                <option value="oferta">⭐ Producto Estrella / Oferta Flash</option>
                                <option value="mayorista">🚚 Venta Mayorista y Eventos</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Tema de Fondo</label>
                            <select id="controlTemaFondo" class="form-select">
                                <option value="theme-glaciar" selected>🧊 Azul Glaciar (Estándar)</option>
                                <option value="theme-polar-night">🌌 Noche Polar Profunda</option>
                                <option value="theme-cyber-ice">⚡ Cyber Ice Neón</option>
                                <option value="theme-arctic-frost">❄️ Escarcha Ártica</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 2. Personalización de Textos -->
                <div class="control-section">
                    <h2 class="h6 fw-bold text-primary mb-3">
                        <i class="bi bi-fonts me-2"></i> 2. Textos y Llamados a la Acción
                    </h2>

                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Badge Superior</label>
                            <input type="text" id="controlBadge" class="form-control" value="❄️ ¡PRECIOS DE FÁBRICA!">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Título de la Historia</label>
                            <input type="text" id="controlTitulo" class="form-control" value="BOLSAS DE HIELO">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Bajada / Descripción</label>
                            <input type="text" id="controlSubtitulo" class="form-control" value="Rolitos 100% puros y cristalinos • Venta Mayorista y Minorista">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">WhatsApp de Pedidos</label>
                            <input type="text" id="controlWhatsapp" class="form-control" value="+54 9 11 4567-8900">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Texto Pie / Ubicación</label>
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
                        <span class="badge text-bg-light border text-muted">Sincronizado</span>
                    </div>

                    <p class="small text-muted mb-3">
                        Seleccioná qué presentaciones de hielo querés incluir en la historia (máximo 5 para legibilidad perfecta en Instagram).
                    </p>

                    <div class="table-responsive border rounded bg-light p-2">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="small text-muted">
                                <tr>
                                    <th style="width: 40px;" class="text-center">Mostrar</th>
                                    <th>Presentación / Bolsa</th>
                                    <th>Precio Base Firestore</th>
                                    <th>Precio en Story ($)</th>
                                </tr>
                            </thead>
                            <tbody id="tablaProductosStories">
                                <?php if (empty($productosFirestore)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No hay productos registrados en Firestore.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($productosFirestore as $idx => $p): ?>
                                        <tr>
                                            <td class="text-center">
                                                <input class="form-check-input check-prod-story" type="checkbox" value="<?= $p['id'] ?>" <?= $idx < 4 ? 'checked' : '' ?> data-id="<?= $p['id'] ?>">
                                            </td>
                                            <td>
                                                <strong class="small text-dark"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span class="badge text-bg-light border small text-muted ms-1"><?= ucfirst($p['presentacion']) ?></span>
                                            </td>
                                            <td class="small text-muted font-monospace">
                                                $ <?= number_format($p['precio'], 2, ',', '.') ?>
                                            </td>
                                            <td>
                                                <input type="number" step="1" class="form-control form-control-sm input-precio-story" style="max-width: 120px;" value="<?= (int)$p['precio'] ?>" data-id="<?= $p['id'] ?>" data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>" data-pres="<?= htmlspecialchars($p['presentacion'], ENT_QUOTES, 'UTF-8') ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Columna Derecha: Vista Previa y Botón de Descarga -->
            <div class="col-12 col-xl-5">
                
                <div class="phone-mockup-wrapper">
                    <div class="d-flex flex-column align-items-center">
                        
                        <!-- Marco de Celular con Story -->
                        <div class="phone-frame mb-3">
                            <div class="phone-screen">
                                <div id="storyCanvasExport" class="story-canvas theme-glaciar">
                                    
                                    <!-- Header de la Historia -->
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="ice-badge-top" id="storyPreviewBadge">
                                                ❄️ ¡PRECIOS DE FÁBRICA!
                                            </span>
                                            <div class="text-white-50 small" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em;">
                                                DISTRIBUIDORA
                                            </div>
                                        </div>

                                        <h2 class="story-title text-white mb-1" id="storyPreviewTitulo">
                                            BOLSAS DE HIELO
                                        </h2>
                                        <p class="story-subtitle text-white-50 mb-0" id="storyPreviewSubtitulo">
                                            Rolitos 100% puros y cristalinos • Venta Mayorista y Minorista
                                        </p>
                                    </div>

                                    <!-- Lista de Tarjetas de Productos y Precios -->
                                    <div class="story-cards-container" id="storyPreviewProductosContainer">
                                        <!-- Render dinámico de productos -->
                                    </div>

                                    <!-- Footer con WhatsApp y Contacto -->
                                    <div class="story-footer-cta">
                                        <div class="story-whatsapp-btn mb-1">
                                            <i class="bi bi-whatsapp fs-6"></i>
                                            <span id="storyPreviewWhatsapp">PEDIDOS: +54 9 11 4567-8900</span>
                                        </div>
                                        <div class="story-footer-text text-white-50" id="storyPreviewUbicacion">
                                            Envíos a Kioscos, Bares y Eventos • Planta Central
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Botones de Descarga y Compartir -->
                        <div class="d-flex flex-column gap-2 w-100" style="max-width: 375px;">
                            <button type="button" class="btn btn-success btn-lg fw-bold shadow" id="btnDescargarHistoriaPng">
                                <i class="bi bi-download me-2"></i> Descargar Historia PNG
                            </button>
                            <button type="button" class="btn btn-outline-dark fw-semibold" id="btnCopiarCaption">
                                <i class="bi bi-card-text me-2"></i> Copiar Texto / Caption para Instagram
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
            // 1. Tema
            canvasStory.className = `story-canvas ${selectTema.value}`;

            // 2. Textos
            previewBadge.textContent = inputBadge.value || "❄️ FÁBRICA DE HIELO";
            previewTitulo.textContent = inputTitulo.value || "BOLSAS DE HIELO";
            previewSubtitulo.textContent = inputSubtitulo.value || "Calidad y Pureza Garantizada";
            previewWhatsapp.textContent = `PEDIDOS: ${inputWhatsapp.value || '+54 9 11 0000-0000'}`;
            previewUbicacion.textContent = inputUbicacion.value || "Reparto Directo a Comercios";

            // 3. Productos seleccionados
            const checkboxes = document.querySelectorAll(".check-prod-story:checked");
            previewContainerProds.innerHTML = "";

            const plantilla = selectTipoPlantilla.value;

            if (checkboxes.length === 0) {
                previewContainerProds.innerHTML = `
                    <div class="p-3 text-center bg-white bg-opacity-10 rounded-3 small">
                        Seleccioná productos en la tabla para visualizarlos en la historia.
                    </div>
                `;
                return;
            }

            checkboxes.forEach((chk, index) => {
                const id = chk.value;
                const inputPrecio = document.querySelector(`.input-precio-story[data-id="${id}"]`);
                const nombre = inputPrecio ? inputPrecio.dataset.nombre : "Bolsa de Hielo";
                const pres = inputPrecio ? inputPrecio.dataset.pres : "unidad";
                const precioNum = inputPrecio ? parseFloat(inputPrecio.value) || 0 : 0;

                const card = document.createElement("div");
                card.className = "story-item-card";

                if (plantilla === "oferta" && index === 0) {
                    card.style.background = "rgba(255, 255, 255, 0.25)";
                    card.style.border = "2px solid #38bdf8";
                    card.style.padding = "14px";
                    card.innerHTML = `
                        <div>
                            <span class="badge bg-warning text-dark fw-bold mb-1">⭐ PROMO DESTACADA</span>
                            <div class="story-item-name fs-5">${nombre}</div>
                            <div class="story-item-pres">Bolsa individual de máxima pureza</div>
                        </div>
                        <div class="story-item-price fs-2">${formatoMoneda.format(precioNum)}</div>
                    `;
                } else {
                    card.innerHTML = `
                        <div>
                            <div class="story-item-name">${nombre}</div>
                            <div class="story-item-pres">${pres === 'caja' ? 'Caja cerrada' : pres === 'bulto' ? 'Pack / Bulto' : 'Bolsa individual'}</div>
                        </div>
                        <div class="story-item-price">${formatoMoneda.format(precioNum)}</div>
                    `;
                }

                previewContainerProds.appendChild(card);
            });
        }

        // Listeners de actualización en tiempo real
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

        // Cambiar plantilla con valores predeterminados
        selectTipoPlantilla.addEventListener("change", () => {
            const p = selectTipoPlantilla.value;
            if (p === "oferta") {
                inputBadge.value = "🔥 ¡OFERTA FLASH EN HIELO!";
                inputTitulo.value = "PROMO DEL DÍA";
                inputSubtitulo.value = "Aprovechá nuestros precios especiales por tiempo limitado.";
            } else if (p === "mayorista") {
                inputBadge.value = "🚚 REPARTO MAYORISTA";
                inputTitulo.value = "PROVEEDOR DE HIELO";
                inputSubtitulo.value = "Abastecimiento constante a Kioscos, Bares, Eventos y Gastronomía.";
            } else {
                inputBadge.value = "❄️ ¡PRECIOS DE FÁBRICA!";
                inputTitulo.value = "BOLSAS DE HIELO";
                inputSubtitulo.value = "Rolitos 100% puros y cristalinos • Venta Mayorista y Minorista";
            }
            actualizarStory();
        });

        // 4. Descargar Historia en Formato PNG 1080x1920
        document.getElementById("btnDescargarHistoriaPng").addEventListener("click", async () => {
            const btn = document.getElementById("btnDescargarHistoriaPng");
            const textoOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Generando imagen HD...`;

            try {
                const elemento = document.getElementById("storyCanvasExport");
                
                // Renderizar con html2canvas en alta definición (escala 3x para 1080x1920 nítido)
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
                `❄️ *${inputTitulo.value.toUpperCase()} - LISTA DE PRECIOS* ❄️\n\n` +
                `${inputSubtitulo.value}\n\n` +
                `📋 *Precios vigentes:*\n` +
                `${listaPrecios}\n` +
                `🚚 *Reparto y Pedidos:* ${inputWhatsapp.value}\n` +
                `📍 ${inputUbicacion.value}\n\n` +
                `#Hielo #FabricaDeHielo #Rolitos #BolsasDeHielo #Bebidas #Eventos #Gastronomia`;

            try {
                await navigator.clipboard.writeText(caption);
                alert("¡Texto y pie de foto copiado al portapapeles listo para Instagram!");
            } catch {
                prompt("Copia el siguiente texto:", caption);
            }
        });

        // Inicializar
        document.addEventListener("DOMContentLoaded", actualizarStory);
    </script>
</body>
</html>
