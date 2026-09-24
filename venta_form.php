<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin", "vendedor"]);
require_once __DIR__ . "/FirestoreConexion.php";

$rol = $_SESSION["usuario_rol"] ?? "vendedor";
$esAdmin = ($rol === "admin");
$limiteDescuento = $esAdmin ? 100 : (int)($_SESSION["usuario_limite_descuento"] ?? 15);
$nombreCompleto = trim(($_SESSION["usuario_nombre"] ?? "Usuario") . " " . ($_SESSION["usuario_apellido"] ?? ""));
$paginaRetorno = $esAdmin ? "admin.php" : "vendedor.php";
$csrf = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta / Venta de Hielo | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        .item-resultado-busqueda {
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        .item-resultado-busqueda:hover, .item-resultado-busqueda.active-item {
            background-color: #f1f5f9;
        }
        .tarjeta-prod-seleccionado {
            border-left: 4px solid #0d6efd !important;
        }
    </style>
</head>
<body data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, "UTF-8") ?>" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, "UTF-8") ?>" data-limite-descuento="<?= $limiteDescuento ?>">
    <!-- Barra Superior -->
    <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $paginaRetorno ?>">
                <span class="marca-icono" aria-hidden="true">❄️</span>
                <span class="fw-bold">Punto de Venta — Fábrica & Distribución de Hielo</span>
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="<?= $paginaRetorno ?>" class="btn btn-outline-secondary btn-sm">← Volver al Panel</a>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <!-- Encabezado -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <a href="<?= $paginaRetorno ?>" class="text-decoration-none text-muted small">← Volver al Panel</a>
                <h1 class="h3 fw-bold mt-1 mb-0">Registrar Venta de Hielo</h1>
                <p class="text-muted small mb-0">Carga rápida de datos de cliente en el acto, selección de bolsas y rollos de hielo, y emisión de ticket.</p>
            </div>
        </div>

        <!-- Alertas -->
        <div id="alertaError" class="alert alert-danger d-none mb-3" role="alert"></div>
        <div id="alertaExito" class="alert alert-success d-none mb-3" role="alert"></div>

        <div class="row g-4">
            <!-- Columna Izquierda: Datos del Cliente Rápido y Búsqueda de Productos -->
            <div class="col-12 col-lg-6">
                <!-- 1. Datos Rápidos del Cliente (Sin pre-registro obligatorio) -->
                <div class="seccion-card mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h2 class="h5 fw-bold text-primary mb-0">1. Datos del Cliente / Entrega</h2>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnConsumidorFinal">Consumidor Final</button>
                    </div>
                    <p class="text-muted small mb-3">Ingresá los datos del cliente directamente en este momento de la venta.</p>
                    
                    <div class="row g-2">
                        <div class="col-12">
                            <label for="clienteNombre" class="form-label fw-semibold">Nombre / Razón Social *</label>
                            <input type="text" class="form-control" id="clienteNombre" placeholder="Ej: Maxikiosco Los Pinos / Restaurante El Faro / Juan Pérez" value="Consumidor Final" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="clienteTelefono" class="form-label small text-muted">Teléfono / WhatsApp</label>
                            <input type="tel" class="form-control" id="clienteTelefono" placeholder="Ej: +54 9 11 5555-4444">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="clienteDireccion" class="form-label small text-muted">Dirección / Punto de Entrega</label>
                            <input type="text" class="form-control" id="clienteDireccion" placeholder="Ej: Av. San Martín 1234, Local 2">
                        </div>
                    </div>
                </div>

                <!-- 2. Buscador Inteligente de Presentaciones de Hielo -->
                <div class="seccion-card mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                        <h2 class="h5 fw-bold text-primary mb-0">2. Buscar y Agregar Presentación de Hielo</h2>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-secondary" type="button" id="btnSincronizarProds" title="Actualizar catálogo en tiempo real">
                                Sincronizar
                            </button>
                            <button class="btn btn-sm btn-outline-primary" type="button" id="btnToggleQuickPick" title="Abrir catálogo rápido">
                                Selección Rápida
                            </button>
                        </div>
                    </div>

                    <!-- Filtros Rápidos por Categoría -->
                    <div class="d-flex gap-1 overflow-x-auto pb-2 mb-2" id="pillsCategoriasPOS" style="scrollbar-width: thin;">
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 text-nowrap pill-pos active" data-categoria="">Todos</button>
                    </div>
                    
                    <div class="mb-3 position-relative">
                        <label for="buscadorVentaProducto" class="form-label fw-bold">Buscar Presentación de Hielo *</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="buscadorVentaProducto" placeholder="Escanear código o escribir (ej: Bolsa 2kg, Rollo 5kg, Cubos, Barra)..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btnLimpiarBuscadorProd" title="Limpiar búsqueda">Limpiar</button>
                            <button class="btn btn-outline-primary" type="button" id="btnEscanearProductoCb" title="Escanear código con cámara">Escanear</button>
                        </div>
                        
                        <!-- Lista flotante de sugerencias en vivo -->
                        <div id="dropdownResultadosBusqueda" class="position-absolute w-100 bg-white border rounded-3 shadow-lg mt-1 d-none" style="z-index: 1050; max-height: 300px; overflow-y: auto;">
                        </div>

                        <!-- Selector sincronizado interno -->
                        <select class="form-select d-none" id="ventaProducto">
                            <option value="">Cargando presentaciones de hielo...</option>
                        </select>
                    </div>

                    <!-- Panel Colapsable de Selección Rápida (Quick-Pick Grid) -->
                    <div id="panelQuickPick" class="border rounded-3 p-2 mb-3 bg-light d-none" style="max-height: 260px; overflow-y: auto;">
                        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                            <span class="small fw-bold text-muted" id="quickPickTitulo">Catálogo Rápido de Hielo</span>
                            <small class="text-muted">Clic para seleccionar</small>
                        </div>
                        <div class="row g-2" id="gridQuickPick"></div>
                    </div>

                    <!-- Ficha del Producto Seleccionado -->
                    <div id="tarjetaProductoSeleccionado" class="p-3 bg-white rounded-3 border tarjeta-prod-seleccionado mb-3 shadow-sm d-none">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <span class="badge bg-primary rounded-pill mb-1" id="selProdCat">Hielo en Cubos</span>
                                <h3 class="h6 fw-bold mb-0 text-dark" id="selProdNombre">Bolsa de Hielo en Cubos 2kg</h3>
                                <small class="text-muted" id="selProdDetalles">Cód: —</small>
                            </div>
                            <div class="text-end">
                                <span class="badge text-bg-success fs-6 fw-bold" id="selProdPrecio">$ 0,00</span>
                                <small class="d-block text-muted" id="selProdStock">Stock: 0 un.</small>
                            </div>
                        </div>
                        <small id="ventaInfoEmpaque" class="text-primary small d-none mt-2 d-block"></small>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-12 col-sm-4">
                            <label for="ventaTipoVenta" class="form-label small text-muted">Presentación</label>
                            <select class="form-select" id="ventaTipoVenta">
                                <option value="unidad">Bolsa / Unidad</option>
                                <option value="caja">Caja</option>
                                <option value="bulto">Bulto / Pack</option>
                            </select>
                        </div>
                        <div class="col-6 col-sm-4">
                            <label for="ventaCantidad" class="form-label small text-muted">Cantidad *</label>
                            <input class="form-control fw-bold" id="ventaCantidad" type="number" min="1" value="1">
                        </div>
                        <div class="col-6 col-sm-4">
                            <label for="ventaDescuento" class="form-label small text-muted">Descuento (%)</label>
                            <select class="form-select" id="ventaDescuento">
                                <option value="0" selected>0%</option>
                                <option value="5">5%</option>
                                <option value="10">10%</option>
                                <option value="15">15%</option>
                                <option value="20">20%</option>
                                <option value="25">25%</option>
                                <option value="custom">Otro...</option>
                            </select>
                            <input type="number" min="0" max="<?= $limiteDescuento ?>" class="form-control mt-1 d-none" id="ventaDescuentoCustom" placeholder="Máx <?= $limiteDescuento ?>%">
                        </div>
                    </div>

                    <!-- Previsualización del Artículo -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Unidades / Bolsas físicas:</span>
                            <strong id="itemPreUnidades" class="text-dark">0 un.</strong>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Precio unitario lista:</span>
                            <span id="itemPrePrecio">$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between small fw-semibold text-primary mb-1">
                            <span>Precio unitario con descuento:</span>
                            <strong id="itemPrePrecioDesc" class="text-primary">$ 0,00</strong>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Subtotal ítem:</span>
                            <span id="itemPreSubtotal">$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold text-success pt-1 border-top">
                            <span>Subtotal con descuento:</span>
                            <span id="itemPreTotal">$ 0,00</span>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary w-100 py-2 fs-6 fw-bold" id="btnAgregarAlCarrito">
                        + Agregar al Carrito
                    </button>
                </div>
            </div>

            <!-- Columna Derecha: Detalle del Carrito y Confirmación -->
            <div class="col-12 col-lg-6">
                <div class="seccion-card mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h5 fw-bold text-dark mb-0">3. Ticket de Compra</h2>
                        <span class="badge text-bg-primary" id="carritoCountBadge">0 ítems</span>
                    </div>

                    <!-- Tabla de Carrito -->
                    <div class="table-responsive border rounded mb-3 bg-white">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th>#</th>
                                    <th>Presentación</th>
                                    <th>Precio</th>
                                    <th>Desc.</th>
                                    <th>Total</th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody id="carritoTablaBody">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        El carrito está vacío. Agregá bolsas de hielo desde el panel izquierdo.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Resumen Financiero -->
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="d-flex justify-content-between text-muted mb-2">
                            <span>Total bolsas/unidades:</span>
                            <strong id="resumenTotalUnidades" class="text-dark">0 un.</strong>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-2">
                            <span>Subtotal:</span>
                            <span id="resumenSubtotal">$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-2">
                            <span>Descuentos aplicados:</span>
                            <span id="resumenDescuento" class="text-danger">$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between fs-4 fw-bold text-success pt-2 border-top">
                            <span>TOTAL A COBRAR:</span>
                            <span id="resumenTotalFinal">$ 0,00</span>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger" id="btnVaciarCarrito">Vaciar Carrito</button>
                        <button type="button" class="btn btn-success flex-grow-1 py-3 fs-5 fw-bold shadow" id="btnConfirmarVenta" disabled>
                            Confirmar y Registrar Venta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Escáner Cámara Standalone -->
    <div class="modal fade" id="modalScannerCamara" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5 fw-bold">Escanear Código con Cámara</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="contenedorLectorCamara" class="p-2 mb-3">
                        <div id="qr-reader"></div>
                    </div>
                    <div id="scannerResultado" class="alert alert-info d-none mb-0 py-2 small"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Venta Registrada con Remito PDF -->
    <div class="modal fade" id="modalVentaExitosa" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white py-3">
                    <h2 class="modal-title fs-5 fw-bold mb-0">
                        <i class="bi bi-check-circle-fill me-2"></i> ¡Venta Registrada con Éxito!
                    </h2>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <div class="display-6 text-success fw-bold" id="modalExitoTotal">$ 0,00</div>
                        <p class="text-muted mb-1" id="modalExitoCliente">Cliente: Consumidor Final</p>
                        <span class="badge text-bg-light border font-monospace fs-6" id="modalExitoTicket">TK-000000</span>
                    </div>

                    <div class="p-3 bg-light rounded-3 border text-start mb-4 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Total bolsas/unidades:</span>
                            <strong id="modalExitoUnidades">0 un.</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Descuento aplicado:</span>
                            <span id="modalExitoDescuento" class="text-danger">$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Comprobante comercial:</span>
                            <span class="badge text-bg-primary">Remito Oficial Listo</span>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="#" target="_blank" class="btn btn-primary btn-lg fw-bold shadow-sm" id="btnModalVerRemito">
                            <i class="bi bi-file-earmark-pdf-fill me-2"></i> Generar / Ver Remito PDF
                        </a>
                        <button type="button" class="btn btn-success fw-semibold" id="btnModalWhatsapp">
                            <i class="bi bi-whatsapp me-2"></i> Compartir por WhatsApp
                        </button>
                    </div>
                </div>
                <div class="modal-footer bg-light justify-content-between p-3">
                    <button type="button" class="btn btn-outline-secondary" id="btnModalNuevaVenta">
                        <i class="bi bi-plus-circle me-1"></i> Nueva Venta
                    </button>
                    <a href="<?= $paginaRetorno ?>" class="btn btn-dark">
                        <i class="bi bi-arrow-left-circle me-1"></i> Volver al Listado
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const formatoMoneda = new Intl.NumberFormat("es-AR", { style: "currency", currency: "ARS" });
        const rol = document.body.dataset.rol || "vendedor";
        const limiteDescuento = parseInt(document.body.dataset.limiteDescuento) || 15;
        const csrfToken = document.body.dataset.csrf;

        let productosCache = [];
        let categoriasCache = [];
        let carrito = [];
        let callbackScanActivo = null;
        let categoriaFiltroActiva = "";
        let indiceFocoResultado = -1;

        // Botón rápido para consumidor final
        document.getElementById("btnConsumidorFinal").addEventListener("click", () => {
            document.getElementById("clienteNombre").value = "Consumidor Final";
            document.getElementById("clienteTelefono").value = "";
            document.getElementById("clienteDireccion").value = "";
        });

        // Cargar categorías y productos
        async function inicializarPOS() {
            try {
                const [prods, cats] = await Promise.all([
                    fetch("obtener_productos.php").then(r => r.json()).catch(() => []),
                    fetch("obtener_categorias.php").then(r => r.json()).catch(() => [])
                ]);

                productosCache = Array.isArray(prods) ? prods : [];
                categoriasCache = Array.isArray(cats) ? cats : [];

                // Poblar select productos (interno)
                actualizarSelectorProductosInterno();

                // Poblar pills de categorías para filtro rápido
                renderizarPillsCategorias();

                // Renderizar Quick Pick inicial
                renderizarQuickPick();

                recalcularPrevisualizacion();

            } catch (err) {
                console.error("Error al inicializar POS de hielo:", err);
            }
        }

        function actualizarSelectorProductosInterno() {
            const selProds = document.getElementById("ventaProducto");
            selProds.replaceChildren(new Option("-- Seleccionar Presentación de Hielo --", ""));
            productosCache.forEach(p => {
                const cat = p.categoria_nombre ? ` [${p.categoria_nombre}]` : "";
                const opt = new Option(`${p.nombre}${cat} (Stock: ${p.stock} un. - ${formatoMoneda.format(p.precio_venta)})`, p.id);
                opt.dataset.precio = p.precio_venta;
                opt.dataset.stock = p.stock;
                opt.dataset.presentacion = p.presentacion || "unidad";
                opt.dataset.permiteVentaUnidad = (p.permite_venta_unidad !== false && p.permite_venta_unidad !== 0 && p.permite_venta_unidad !== "0") ? "1" : "0";
                opt.dataset.unidadesBulto = p.unidades_por_bulto || 1;
                opt.dataset.proveedor = p.proveedor || "";
                opt.dataset.categoria = p.categoria_nombre || "";
                opt.dataset.nombre = p.nombre;
                opt.dataset.codigo = p.codigo_barras || "";
                opt.dataset.descripcion = p.descripcion || "";
                selProds.appendChild(opt);
            });
        }

        function renderizarPillsCategorias() {
            const cont = document.getElementById("pillsCategoriasPOS");
            if (!cont) return;

            cont.innerHTML = `
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 text-nowrap pill-pos active" data-categoria="">
                    Todos
                </button>
            `;

            const nombresCats = new Set();
            categoriasCache.forEach(c => { if (c.nombre) nombresCats.add(c.nombre.trim()); });
            productosCache.forEach(p => {
                const cNom = p.categoria_nombre || p.categoria;
                if (cNom && cNom.trim() !== "") nombresCats.add(cNom.trim());
            });

            nombresCats.forEach(catNom => {
                const btn = document.createElement("button");
                btn.type = "button";
                btn.className = "btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 text-nowrap pill-pos";
                btn.setAttribute("data-categoria", catNom);
                btn.textContent = catNom;
                cont.appendChild(btn);
            });

            cont.querySelectorAll(".pill-pos").forEach(btn => {
                btn.addEventListener("click", () => {
                    cont.querySelectorAll(".pill-pos").forEach(b => {
                        b.classList.remove("btn-primary", "active");
                        b.classList.add("btn-outline-secondary");
                    });
                    btn.classList.remove("btn-outline-secondary");
                    btn.classList.add("btn-primary", "active");
                    categoriaFiltroActiva = btn.getAttribute("data-categoria") || "";
                    
                    const query = inputBuscador.value.trim();
                    if (query !== "") {
                        renderizarResultadosBusqueda(query);
                    } else if (categoriaFiltroActiva !== "") {
                        renderizarResultadosBusqueda(categoriaFiltroActiva);
                    } else {
                        dropdownResultados.classList.add("d-none");
                    }
                    renderizarQuickPick();
                });
            });
        }

        function renderizarQuickPick() {
            const grid = document.getElementById("gridQuickPick");
            const lblTitulo = document.getElementById("quickPickTitulo");
            if (!grid) return;

            let prodsMostrar = productosCache;
            if (categoriaFiltroActiva !== "") {
                const cLower = categoriaFiltroActiva.toLowerCase();
                prodsMostrar = prodsMostrar.filter(p => (p.categoria_nombre || p.categoria || "").toLowerCase() === cLower);
                lblTitulo.textContent = `Hielo en "${categoriaFiltroActiva}" (${prodsMostrar.length})`;
            } else {
                lblTitulo.textContent = `Presentaciones Rápidas (${prodsMostrar.length})`;
            }

            if (prodsMostrar.length === 0) {
                grid.innerHTML = `<div class="col-12 text-center text-muted small py-2">No hay artículos registrados para esta categoría.</div>`;
                return;
            }

            grid.replaceChildren();
            prodsMostrar.slice(0, 12).forEach(p => {
                const col = document.createElement("div");
                col.className = "col-6 col-sm-4 col-md-3";

                const card = document.createElement("div");
                card.className = "p-2 bg-white border rounded-3 h-100 d-flex flex-column justify-content-between shadow-xs";
                card.style.cursor = "pointer";

                const stockBadge = p.stock > 0 ? `<span class="badge text-bg-light text-muted small">${p.stock} un.</span>` : `<span class="badge text-bg-danger small">Agotado</span>`;

                card.innerHTML = `
                    <div class="mb-1">
                        <span class="badge bg-secondary bg-opacity-25 text-dark small mb-1 text-truncate d-inline-block" style="max-width: 100%; font-size: 0.7rem;">${escapeHtml(p.categoria_nombre || "Hielo")}</span>
                        <div class="fw-bold text-dark small text-truncate" title="${escapeHtml(p.nombre)}">${escapeHtml(p.nombre)}</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1 pt-1 border-top">
                        <span class="fw-bold text-success small">${formatoMoneda.format(p.precio_venta)}</span>
                        ${stockBadge}
                    </div>
                `;

                card.addEventListener("click", () => {
                    seleccionarProducto(p);
                });

                col.appendChild(card);
                grid.appendChild(col);
            });
        }

        // Búsqueda por Código de Barras
        async function buscarYSeleccionarPorCodigo(codigoRaw) {
            const clean = String(codigoRaw || "").trim();
            if (!clean) return false;

            const cleanLower = clean.toLowerCase();
            const cleanSinCeros = clean.replace(/^0+/, "");

            let prod = productosCache.find(p => {
                const cb = String(p.codigo_barras || "").trim().toLowerCase();
                const cod = String(p.codigo || "").trim().toLowerCase();
                const id = String(p.id || "").trim().toLowerCase();
                const cbSinCeros = cb.replace(/^0+/, "");

                return cb === cleanLower || 
                       cod === cleanLower || 
                       id === cleanLower || 
                       (cleanSinCeros !== "" && cbSinCeros === cleanSinCeros);
            });

            if (!prod) {
                try {
                    const resp = await fetch(`obtener_productos.php?codigo_barras=${encodeURIComponent(clean)}`);
                    const lista = await resp.json().catch(() => []);
                    if (Array.isArray(lista) && lista.length > 0) {
                        prod = lista[0];
                        if (!productosCache.some(p => p.id === prod.id)) {
                            productosCache.push(prod);
                            actualizarSelectorProductosInterno();
                        }
                    }
                } catch (e) {
                    console.warn("Fallo búsqueda remota de código de barras:", e);
                }
            }

            if (prod) {
                seleccionarProducto(prod);
                return true;
            } else {
                alert(`No se encontró ningún producto con el código "${clean}".`);
                return false;
            }
        }

        // Sincronizar Catálogo
        const btnSync = document.getElementById("btnSincronizarProds");
        if (btnSync) {
            btnSync.addEventListener("click", async () => {
                btnSync.disabled = true;
                const originalHtml = btnSync.innerHTML;
                btnSync.innerHTML = `<span class="spinner-border spinner-border-sm"></span>`;

                try {
                    const resp = await fetch("obtener_productos.php");
                    const prods = await resp.json();
                    if (Array.isArray(prods)) {
                        productosCache = prods;
                        actualizarSelectorProductosInterno();
                        renderizarPillsCategorias();
                        renderizarQuickPick();
                        const query = inputBuscador.value.trim();
                        if (query) renderizarResultadosBusqueda(query);
                    }
                } catch (e) {
                    console.error("Error al sincronizar productos:", e);
                } finally {
                    btnSync.disabled = false;
                    btnSync.innerHTML = originalHtml;
                }
            });
        }

        // Toggle Quick Pick
        const btnToggleQP = document.getElementById("btnToggleQuickPick");
        const panelQP = document.getElementById("panelQuickPick");
        if (btnToggleQP && panelQP) {
            btnToggleQP.addEventListener("click", () => {
                panelQP.classList.toggle("d-none");
                if (!panelQP.classList.contains("d-none")) {
                    renderizarQuickPick();
                }
            });
        }

        // Buscador Inteligente
        const inputBuscador = document.getElementById("buscadorVentaProducto");
        const dropdownResultados = document.getElementById("dropdownResultadosBusqueda");
        const tarjetaSeleccionado = document.getElementById("tarjetaProductoSeleccionado");
        const btnLimpiarBuscador = document.getElementById("btnLimpiarBuscadorProd");

        function renderizarResultadosBusqueda(query) {
            const q = query.trim().toLowerCase();
            if (!q && categoriaFiltroActiva === "") {
                dropdownResultados.classList.add("d-none");
                dropdownResultados.replaceChildren();
                return;
            }

            const catFiltroLower = categoriaFiltroActiva.toLowerCase();

            const filtrados = productosCache.filter(p => {
                if (catFiltroLower !== "") {
                    const cNom = (p.categoria_nombre || p.categoria || "").toLowerCase();
                    if (cNom !== catFiltroLower) return false;
                }

                if (!q) return true;

                const nom = (p.nombre || "").toLowerCase();
                const cat = (p.categoria_nombre || p.categoria || "").toLowerCase();
                const cb = String(p.codigo_barras || "").toLowerCase();
                const cod = String(p.codigo || "").toLowerCase();
                const desc = (p.descripcion || "").toLowerCase();
                const idStr = String(p.id);

                return nom.includes(q) || cat.includes(q) || cb.includes(q) || cod.includes(q) || desc.includes(q) || idStr === q;
            });

            if (filtrados.length === 0) {
                dropdownResultados.innerHTML = `
                    <div class="p-3 text-muted text-center small">
                        No se encontraron productos coincidentes con "<strong>${escapeHtml(query)}</strong>".
                    </div>
                `;
                dropdownResultados.classList.remove("d-none");
                return;
            }

            dropdownResultados.replaceChildren();
            filtrados.slice(0, 15).forEach((p, index) => {
                const itemDiv = document.createElement("div");
                itemDiv.className = "p-2 border-bottom item-resultado-busqueda d-flex justify-content-between align-items-center";
                itemDiv.dataset.id = p.id;
                itemDiv.dataset.index = index;

                const stockClass = p.stock > 10 ? "text-bg-success" : (p.stock > 0 ? "text-bg-warning" : "text-bg-danger");
                const stockText = p.stock > 0 ? `${p.stock} un.` : "Sin stock";

                const presNom = (p.presentacion || "unidad").toLowerCase();
                const permiteUnid = (presNom === "unidad") || (p.permite_venta_unidad !== false && p.permite_venta_unidad !== 0 && p.permite_venta_unidad !== "0");
                let badgePres = "";
                if (presNom === "caja" || presNom === "bulto") {
                    badgePres = permiteUnid 
                        ? `<span class="badge text-bg-info ms-1">${presNom.toUpperCase()} / BOLSA</span>` 
                        : `<span class="badge text-bg-warning ms-1">SOLO ${presNom.toUpperCase()}</span>`;
                }

                itemDiv.innerHTML = `
                    <div class="me-2 text-truncate">
                        <div class="fw-bold text-dark text-truncate">${escapeHtml(p.nombre)} ${badgePres}</div>
                        <div class="small text-muted text-truncate">
                            <span class="badge text-bg-light border">${escapeHtml(p.categoria_nombre || "Hielo")}</span>
                            ${p.codigo_barras ? `<span class="font-monospace ms-1 text-primary">${escapeHtml(p.codigo_barras)}</span>` : ""}
                        </div>
                    </div>
                    <div class="text-end text-nowrap">
                        <span class="fw-bold text-success fs-6">${formatoMoneda.format(p.precio_venta)}</span>
                        <span class="badge ${stockClass} d-block mt-1">${stockText}</span>
                    </div>
                `;

                itemDiv.addEventListener("click", () => {
                    seleccionarProducto(p);
                });

                dropdownResultados.appendChild(itemDiv);
            });

            dropdownResultados.classList.remove("d-none");
            indiceFocoResultado = -1;
        }

        function seleccionarProducto(p) {
            const selProds = document.getElementById("ventaProducto");
            selProds.value = p.id;

            const pres = (p.presentacion || "unidad").toLowerCase();
            const permiteUnidad = (pres === "unidad") || (p.permite_venta_unidad !== false && p.permite_venta_unidad !== 0 && p.permite_venta_unidad !== "0");
            const unidEmpaque = Math.max(1, parseInt(p.unidades_por_bulto) || 1);

            const selTipo = document.getElementById("ventaTipoVenta");
            selTipo.replaceChildren();

            if (pres === "caja") {
                selTipo.appendChild(new Option(`Caja (${unidEmpaque} un.)`, "caja"));
                if (permiteUnidad) {
                    selTipo.appendChild(new Option("Bolsa / Unidad individual", "unidad"));
                }
                selTipo.value = "caja";
            } else if (pres === "bulto") {
                selTipo.appendChild(new Option(`Bulto / Pack (${unidEmpaque} un.)`, "bulto"));
                if (permiteUnidad) {
                    selTipo.appendChild(new Option("Bolsa / Unidad individual", "unidad"));
                }
                selTipo.value = "bulto";
            } else {
                selTipo.appendChild(new Option("Bolsa / Unidad", "unidad"));
                selTipo.value = "unidad";
            }

            let badgeRestriccion = "";
            if ((pres === "caja" || pres === "bulto") && !permiteUnidad) {
                badgeRestriccion = `<span class="badge text-bg-warning ms-1">Venta por ${pres.toUpperCase()}</span>`;
            }

            document.getElementById("selProdCat").textContent = p.categoria_nombre || "Hielo";
            document.getElementById("selProdNombre").textContent = p.nombre;
            document.getElementById("selProdDetalles").innerHTML = `Cód: ${escapeHtml(p.codigo_barras || p.id)} ${badgeRestriccion}`;
            document.getElementById("selProdPrecio").textContent = formatoMoneda.format(p.precio_venta);
            document.getElementById("selProdStock").textContent = `Stock disponible: ${p.stock} un.`;
            tarjetaSeleccionado.classList.remove("d-none");

            inputBuscador.value = p.nombre;
            dropdownResultados.classList.add("d-none");

            recalcularPrevisualizacion();

            const inCant = document.getElementById("ventaCantidad");
            inCant.focus();
            inCant.select();
        }

        function escapeHtml(text) {
            const div = document.createElement("div");
            div.textContent = text || "";
            return div.innerHTML;
        }

        inputBuscador.addEventListener("input", (e) => {
            renderizarResultadosBusqueda(e.target.value);
        });

        inputBuscador.addEventListener("keydown", (e) => {
            const items = dropdownResultados.querySelectorAll(".item-resultado-busqueda");
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (items.length > 0) {
                    indiceFocoResultado = (indiceFocoResultado + 1) % items.length;
                    items.forEach((it, i) => it.classList.toggle("active-item", i === indiceFocoResultado));
                }
            } else if (e.key === "ArrowUp") {
                e.preventDefault();
                if (items.length > 0) {
                    indiceFocoResultado = (indiceFocoResultado - 1 + items.length) % items.length;
                    items.forEach((it, i) => it.classList.toggle("active-item", i === indiceFocoResultado));
                }
            } else if (e.key === "Enter") {
                e.preventDefault();
                if (indiceFocoResultado >= 0 && items[indiceFocoResultado]) {
                    items[indiceFocoResultado].click();
                } else if (items.length > 0) {
                    items[0].click();
                }
            } else if (e.key === "Escape") {
                dropdownResultados.classList.add("d-none");
            }
        });

        btnLimpiarBuscador.addEventListener("click", () => {
            inputBuscador.value = "";
            dropdownResultados.classList.add("d-none");
            document.getElementById("ventaProducto").value = "";
            tarjetaSeleccionado.classList.add("d-none");
            recalcularPrevisualizacion();
            inputBuscador.focus();
        });

        document.addEventListener("click", (e) => {
            if (!dropdownResultados.contains(e.target) && e.target !== inputBuscador) {
                dropdownResultados.classList.add("d-none");
            }
        });

        // Restricción de descuentos
        const selDesc = document.getElementById("ventaDescuento");
        const inputDescCustom = document.getElementById("ventaDescuentoCustom");
        if (rol === "vendedor") {
            Array.from(selDesc.options).forEach(opt => {
                if (opt.value !== "custom") {
                    const num = parseInt(opt.value);
                    if (num > limiteDescuento) {
                        opt.disabled = true;
                        opt.text = `${num}% (No autorizado)`;
                    }
                }
            });
        }

        selDesc.addEventListener("change", () => {
            if (selDesc.value === "custom") {
                inputDescCustom.classList.remove("d-none");
            } else {
                inputDescCustom.classList.add("d-none");
            }
            recalcularPrevisualizacion();
        });
        inputDescCustom.addEventListener("input", recalcularPrevisualizacion);

        // Previsualización
        const selProducto = document.getElementById("ventaProducto");
        const selTipoVenta = document.getElementById("ventaTipoVenta");
        const inputCantidad = document.getElementById("ventaCantidad");
        const infoEmpaque = document.getElementById("ventaInfoEmpaque");

        selProducto.addEventListener("change", recalcularPrevisualizacion);
        selTipoVenta.addEventListener("change", recalcularPrevisualizacion);
        inputCantidad.addEventListener("input", recalcularPrevisualizacion);

        inputCantidad.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                document.getElementById("btnAgregarAlCarrito").click();
            }
        });

        function obtenerCalculoActual() {
            const opt = selProducto.selectedOptions[0];
            if (!opt || !opt.dataset.precio || !selProducto.value) return null;

            const id = selProducto.value;
            const nombre = opt.dataset.nombre || opt.text.split("(")[0].trim();
            const precio = parseFloat(opt.dataset.precio) || 0;
            const stock = parseInt(opt.dataset.stock) || 0;
            const unidadesBulto = Math.max(1, parseInt(opt.dataset.unidadesBulto) || 1);
            const proveedor = opt.dataset.proveedor || "";
            const tipoVenta = selTipoVenta.value;
            const cantidad = Math.max(1, parseInt(inputCantidad.value) || 1);

            let totalUnidades = cantidad;
            if (tipoVenta === "caja" || tipoVenta === "bulto") {
                totalUnidades = cantidad * unidadesBulto;
            }

            let descPorc = 0;
            if (selDesc.value === "custom") {
                let customVal = parseFloat(inputDescCustom.value) || 0;
                if (customVal > limiteDescuento && rol === "vendedor") {
                    inputDescCustom.value = limiteDescuento;
                    customVal = limiteDescuento;
                }
                descPorc = Math.min(limiteDescuento, Math.max(0, customVal));
            } else {
                let optVal = parseFloat(selDesc.value) || 0;
                if (optVal > limiteDescuento && rol === "vendedor") {
                    selDesc.value = "0";
                    optVal = 0;
                }
                descPorc = optVal;
            }

            const subtotal = totalUnidades * precio;
            const montoDesc = subtotal * (descPorc / 100);
            const total = Math.max(0, subtotal - montoDesc);
            const precioUnitarioConDescuento = descPorc > 0 ? (precio * (1 - (descPorc / 100))) : precio;

            const presProd = (opt.dataset.presentacion || "unidad").toLowerCase();
            const permiteUnid = (presProd === "unidad") || (opt.dataset.permiteVentaUnidad === "1");

            if (tipoVenta === "unidad" && presProd !== "unidad" && !permiteUnid) {
                return { error: `"${nombre}" está configurado para venta exclusiva por ${presProd.toUpperCase()}.` };
            }

            return {
                id: id,
                producto_id: id,
                producto_nombre: nombre,
                proveedor: proveedor,
                stock_disponible: stock,
                precio_unitario: precio,
                precio_unitario_con_descuento: precioUnitarioConDescuento,
                tipo_venta: tipoVenta,
                cantidad: cantidad,
                cantidad_empaque: cantidad,
                unidades_por_bulto: unidadesBulto,
                total_unidades: totalUnidades,
                descuento_porcentaje: descPorc,
                descuento_monto: montoDesc,
                subtotal: subtotal,
                total: total
            };
        }

        function recalcularPrevisualizacion() {
            const calc = obtenerCalculoActual();
            const lblUnid = document.getElementById("itemPreUnidades");
            const lblPrecio = document.getElementById("itemPrePrecio");
            const lblPrecioDesc = document.getElementById("itemPrePrecioDesc");
            const lblSub = document.getElementById("itemPreSubtotal");
            const lblTot = document.getElementById("itemPreTotal");

            if (!calc || calc.error) {
                lblUnid.textContent = "0 un.";
                lblPrecio.textContent = "$ 0,00";
                if (lblPrecioDesc) lblPrecioDesc.textContent = "$ 0,00";
                lblSub.textContent = "$ 0,00";
                lblTot.textContent = "$ 0,00";
                infoEmpaque.classList.add("d-none");
                return;
            }

            if (calc.tipo_venta === "caja" || calc.tipo_venta === "bulto") {
                infoEmpaque.textContent = `1 ${calc.tipo_venta} = ${calc.unidades_por_bulto} bolsas/unidades físicas`;
                infoEmpaque.classList.remove("d-none");
            } else {
                infoEmpaque.classList.add("d-none");
            }

            lblUnid.textContent = `${calc.total_unidades} un.`;
            lblPrecio.textContent = formatoMoneda.format(calc.precio_unitario);
            if (lblPrecioDesc) {
                lblPrecioDesc.textContent = formatoMoneda.format(calc.precio_unitario_con_descuento);
            }
            lblSub.textContent = formatoMoneda.format(calc.subtotal);
            lblTot.textContent = formatoMoneda.format(calc.total);
        }

        // Agregar al carrito
        document.getElementById("btnAgregarAlCarrito").addEventListener("click", () => {
            const errBox = document.getElementById("alertaError");
            errBox.classList.add("d-none");

            const calc = obtenerCalculoActual();
            if (!calc) {
                errBox.textContent = "Buscá y seleccioná una presentación de hielo antes de agregarla.";
                errBox.classList.remove("d-none");
                inputBuscador.focus();
                return;
            }
            if (calc.error) {
                errBox.textContent = calc.error;
                errBox.classList.remove("d-none");
                return;
            }

            const yaEnCarrito = carrito
                .filter(i => String(i.producto_id) === String(calc.producto_id))
                .reduce((s, i) => s + i.total_unidades, 0);

            if ((yaEnCarrito + calc.total_unidades) > calc.stock_disponible) {
                errBox.textContent = `Stock insuficiente para "${calc.producto_nombre}". Stock disponible: ${calc.stock_disponible} un. (Ya hay ${yaEnCarrito} un. en el carrito).`;
                errBox.classList.remove("d-none");
                return;
            }

            carrito.push(calc);
            renderizarCarrito();

            selProducto.value = "";
            inputBuscador.value = "";
            tarjetaSeleccionado.classList.add("d-none");
            inputCantidad.value = 1;
            selTipoVenta.value = "unidad";
            selDesc.value = "0";
            inputDescCustom.value = "";
            inputDescCustom.classList.add("d-none");
            recalcularPrevisualizacion();
            inputBuscador.focus();
        });

        // Renderizar Carrito
        function renderizarCarrito() {
            const tbody = document.getElementById("carritoTablaBody");
            const badgeCount = document.getElementById("carritoCountBadge");
            const resUnid = document.getElementById("resumenTotalUnidades");
            const resSub = document.getElementById("resumenSubtotal");
            const resDesc = document.getElementById("resumenDescuento");
            const resTot = document.getElementById("resumenTotalFinal");
            const btnConfirm = document.getElementById("btnConfirmarVenta");

            if (carrito.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">El carrito está vacío. Agregá bolsas de hielo desde el panel izquierdo.</td></tr>`;
                badgeCount.textContent = "0 ítems";
                resUnid.textContent = "0 un.";
                resSub.textContent = "$ 0,00";
                resDesc.textContent = "$ 0,00";
                resTot.textContent = "$ 0,00";
                btnConfirm.disabled = true;
                return;
            }

            tbody.replaceChildren();
            let totalUnidadesGen = 0;
            let subtotalGen = 0;
            let descuentoGen = 0;
            let totalGen = 0;

            carrito.forEach((item, idx) => {
                totalUnidadesGen += item.total_unidades;
                subtotalGen += item.subtotal;
                descuentoGen += item.descuento_monto;
                totalGen += item.total;

                const tr = document.createElement("tr");

                const tdNum = document.createElement("td");
                tdNum.textContent = idx + 1;
                tdNum.className = "text-muted small";
                tr.appendChild(tdNum);

                const tdProd = document.createElement("td");
                const strong = document.createElement("strong");
                strong.textContent = item.producto_nombre;
                tdProd.appendChild(strong);

                const smallEmp = document.createElement("small");
                smallEmp.className = "d-block text-muted";
                smallEmp.textContent = item.tipo_venta !== "unidad" 
                    ? `${item.cantidad_empaque} ${item.tipo_venta}(s) (${item.total_unidades} bolsas)` 
                    : `${item.total_unidades} bolsas/un.`;
                tdProd.appendChild(smallEmp);
                tr.appendChild(tdProd);

                const tdPrecio = document.createElement("td");
                tdPrecio.className = "small";
                if (item.descuento_porcentaje > 0 && item.precio_unitario_con_descuento) {
                    tdPrecio.innerHTML = `<span class="text-decoration-line-through text-muted">${formatoMoneda.format(item.precio_unitario)}</span><br><strong class="text-primary">${formatoMoneda.format(item.precio_unitario_con_descuento)} c/u</strong>`;
                } else {
                    tdPrecio.textContent = formatoMoneda.format(item.precio_unitario);
                }
                tr.appendChild(tdPrecio);

                const tdDesc = document.createElement("td");
                if (item.descuento_porcentaje > 0) {
                    tdDesc.innerHTML = `<span class="badge text-bg-danger">-${item.descuento_porcentaje}%</span>`;
                } else {
                    tdDesc.innerHTML = `<span class="text-muted small">0%</span>`;
                }
                tr.appendChild(tdDesc);

                const tdTotal = document.createElement("td");
                tdTotal.textContent = formatoMoneda.format(item.total);
                tdTotal.className = "fw-bold text-success";
                tr.appendChild(tdTotal);

                const tdAcc = document.createElement("td");
                tdAcc.className = "text-end";
                const btnDel = document.createElement("button");
                btnDel.type = "button";
                btnDel.className = "btn btn-outline-danger btn-sm py-0 px-2";
                btnDel.innerHTML = "✕";
                btnDel.addEventListener("click", () => {
                    carrito.splice(idx, 1);
                    renderizarCarrito();
                });
                tdAcc.appendChild(btnDel);
                tr.appendChild(tdAcc);

                tbody.appendChild(tr);
            });

            badgeCount.textContent = `${carrito.length} ítems`;
            resUnid.textContent = `${totalUnidadesGen} un.`;
            resSub.textContent = formatoMoneda.format(subtotalGen);
            resDesc.textContent = descuentoGen > 0 ? `-${formatoMoneda.format(descuentoGen)}` : "$ 0,00";
            resTot.textContent = formatoMoneda.format(totalGen);
            btnConfirm.disabled = false;
        }

        document.getElementById("btnVaciarCarrito").addEventListener("click", () => {
            if (carrito.length > 0 && confirm("¿Vaciar todo el ticket de venta?")) {
                carrito = [];
                renderizarCarrito();
            }
        });

        // Escáner Cámara
        let html5Qr = null;
        const modalScan = new bootstrap.Modal(document.getElementById("modalScannerCamara"));

        document.getElementById("btnEscanearProductoCb").addEventListener("click", () => {
            abrirLector(async (cb) => {
                await buscarYSeleccionarPorCodigo(cb);
            });
        });

        function abrirLector(callback) {
            callbackScanActivo = callback;
            modalScan.show();
            setTimeout(() => {
                if (html5Qr) html5Qr.clear().catch(() => {});
                html5Qr = new Html5Qrcode("qr-reader");
                html5Qr.start(
                    { facingMode: "environment" },
                    { fps: 15, qrbox: { width: 250, height: 150 } },
                    (decoded) => {
                        html5Qr.stop().then(() => {
                            html5Qr.clear();
                            html5Qr = null;
                        });
                        modalScan.hide();
                        if (callbackScanActivo) callbackScanActivo(decoded);
                    },
                    () => {}
                ).catch(err => {
                    const res = document.getElementById("scannerResultado");
                    res.textContent = "Error de cámara: " + err;
                    res.classList.remove("d-none");
                });
            }, 300);
        }

        document.getElementById("modalScannerCamara").addEventListener("hidden.bs.modal", () => {
            if (html5Qr) {
                html5Qr.stop().then(() => {
                    html5Qr.clear();
                    html5Qr = null;
                }).catch(() => {});
            }
        });

        // Pistola de Código de Barras USB / Bluetooth
        let bufferTeclasBarcode = "";
        let tiempoUltimaTecla = Date.now();

        document.addEventListener("keydown", async (e) => {
            const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : "";
            if (activeTag === "textarea" || (activeTag === "input" && (document.activeElement.id === "ventaCantidad" || document.activeElement.id === "clienteNombre" || document.activeElement.id === "clienteTelefono" || document.activeElement.id === "clienteDireccion"))) {
                return;
            }

            const ahora = Date.now();
            if (ahora - tiempoUltimaTecla > 80) {
                bufferTeclasBarcode = "";
            }
            tiempoUltimaTecla = ahora;

            if (e.key === "Enter") {
                if (bufferTeclasBarcode.length >= 4) {
                    const barcodeScanned = bufferTeclasBarcode.trim();
                    bufferTeclasBarcode = "";
                    await buscarYSeleccionarPorCodigo(barcodeScanned);
                }
            } else if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
                bufferTeclasBarcode += e.key;
            }
        });

        // Instancia Modal Venta Exitosa
        const modalVentaExitosaEl = document.getElementById("modalVentaExitosa");
        const modalVentaExitosaBs = new bootstrap.Modal(modalVentaExitosaEl);

        document.getElementById("btnModalNuevaVenta").addEventListener("click", () => {
            modalVentaExitosaBs.hide();
            carrito = [];
            renderizarCarrito();
            document.getElementById("clienteNombre").value = "Consumidor Final";
            document.getElementById("clienteTelefono").value = "";
            document.getElementById("clienteDireccion").value = "";
            document.getElementById("btnConfirmarVenta").disabled = true;
            document.getElementById("btnConfirmarVenta").textContent = "Confirmar y Registrar Venta";
            inputBuscador.focus();
            window.scrollTo({ top: 0, behavior: "smooth" });
        });

        // Confirmar Venta Rápida
        document.getElementById("btnConfirmarVenta").addEventListener("click", async () => {
            const errBox = document.getElementById("alertaError");
            const okBox = document.getElementById("alertaExito");
            errBox.classList.add("d-none");
            okBox.classList.add("d-none");

            const nombreCli = document.getElementById("clienteNombre").value.trim();
            const telCli = document.getElementById("clienteTelefono").value.trim();
            const dirCli = document.getElementById("clienteDireccion").value.trim();

            if (!nombreCli) {
                errBox.textContent = "Debés ingresar el Nombre o Razón Social del cliente (o dejar 'Consumidor Final').";
                errBox.classList.remove("d-none");
                document.getElementById("clienteNombre").focus();
                window.scrollTo({ top: 0, behavior: "smooth" });
                return;
            }

            if (carrito.length === 0) {
                errBox.textContent = "El carrito está vacío. Agregá presentaciones de hielo al ticket.";
                errBox.classList.remove("d-none");
                return;
            }

            const btnConfirm = document.getElementById("btnConfirmarVenta");
            btnConfirm.disabled = true;
            btnConfirm.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Procesando venta...`;

            try {
                const resp = await fetch("guardar_venta.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-Token": csrfToken
                    },
                    body: JSON.stringify({
                        cliente_nombre: nombreCli,
                        cliente_telefono: telCli,
                        cliente_direccion: dirCli,
                        items: carrito
                    })
                });

                const data = await resp.json().catch(() => ({}));
                if (!resp.ok) {
                    throw new Error(data.error || "Error al procesar la venta.");
                }

                // Preparar datos del modal de éxito con Remito PDF
                const ticketId = data.ticket_id || "";
                const totalVentaNum = data.total || 0;
                const remitoUrl = `generar_remito.php?ticket_id=${encodeURIComponent(ticketId)}`;

                document.getElementById("modalExitoTotal").textContent = formatoMoneda.format(totalVentaNum);
                document.getElementById("modalExitoCliente").textContent = `Cliente: ${data.cliente || nombreCli}`;
                document.getElementById("modalExitoTicket").textContent = `Ticket: ${ticketId}`;
                document.getElementById("modalExitoUnidades").textContent = `${data.total_unidades || 0} bolsas`;
                document.getElementById("modalExitoDescuento").textContent = data.descuento_total > 0 ? `-${formatoMoneda.format(data.descuento_total)}` : "$ 0,00";

                const btnVerRemito = document.getElementById("btnModalVerRemito");
                btnVerRemito.href = remitoUrl;

                // Configurar botón de WhatsApp
                const btnWp = document.getElementById("btnModalWhatsapp");
                btnWp.onclick = () => {
                    const urlRemitoAbs = window.location.origin + window.location.pathname.replace("venta_form.php", "") + remitoUrl;
                    let telLimpio = telCli.replace(/[^0-9]/g, '');
                    if (telLimpio.startsWith('0')) telLimpio = telLimpio.substring(1);
                    if (telLimpio && !telLimpio.startsWith('54')) telLimpio = '549' + telLimpio;

                    const msg = encodeURIComponent(
                        `*REMITO DE ENTREGA - FÁBRICA DE HIELO*\n` +
                        `Hola *${nombreCli}*, tu pedido de bolsas de hielo fue registrado con éxito.\n\n` +
                        `🧾 *Ticket:* ${ticketId}\n` +
                        `💰 *Total:* ${formatoMoneda.format(totalVentaNum)}\n` +
                        `📄 *Ver/Descargar Remito PDF:* ${urlRemitoAbs}\n\n` +
                        `¡Muchas gracias!`
                    );
                    const wpLink = telLimpio ? `https://api.whatsapp.com/send?phone=${telLimpio}&text=${msg}` : `https://api.whatsapp.com/send?text=${msg}`;
                    window.open(wpLink, '_blank');
                };

                // Mostrar Modal de Remito
                modalVentaExitosaBs.show();

            } catch (err) {
                errBox.textContent = err.message;
                errBox.classList.remove("d-none");
                btnConfirm.disabled = false;
                btnConfirm.textContent = "Confirmar y Registrar Venta";
                window.scrollTo({ top: 0, behavior: "smooth" });
            }
        });

        // Iniciar POS
        document.addEventListener("DOMContentLoaded", inicializarPOS);
    </script>
</body>
</html>
