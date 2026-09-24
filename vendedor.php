<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["vendedor"]);
$nombreCompleto = trim(($_SESSION["usuario_nombre"] ?? "Vendedor") . " " . ($_SESSION["usuario_apellido"] ?? ""));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content">
    <title>Panel de Ventas | Control Stock Hielo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
    <!-- Librerías para Códigos de Barra, Escáner y Códigos QR -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body data-rol="vendedor" data-csrf="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, "UTF-8") ?>" data-limite-descuento="<?= htmlspecialchars((string)($_SESSION['usuario_limite_descuento'] ?? 15), ENT_QUOTES, 'UTF-8') ?>">
    <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="vendedor.php">
                <span class="marca-icono" aria-hidden="true">❄️</span>
                <span class="fw-bold">Control Stock Hielo</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuVendedor" aria-controls="menuVendedor" aria-expanded="false" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuVendedor">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-1 pt-3 pt-lg-0">
                    <a class="nav-link nav-link-app active" href="vendedor.php">Panel de Ventas</a>
                    <a class="nav-link nav-link-app" href="catalogo.php">Catálogo</a>
                    <button class="btn btn-outline-primary btn-sm ms-lg-2" type="button" id="btnAbrirScannerGlobal" title="Escanear código con cámara">Escanear</button>
                    <a class="btn btn-primary btn-sm ms-lg-1" href="venta_form.php">Punto de Venta (POS)</a>
                    <a class="btn btn-outline-danger btn-sm ms-lg-1" href="logout.php">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-md-5">
        <?php if (isset($_GET["mfa_configurado"])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="status">
                Tu autenticador se configuró correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- Hero Section -->
        <section class="hero-panel p-4 p-md-5 mb-4">
            <div class="hero-contenido">
                <p class="etiqueta text-white-50 mb-2">Punto de Venta y Mostrador</p>
                <h1 class="display-6 fw-bold mb-2">Hola, <?= htmlspecialchars($nombreCompleto, ENT_QUOTES, "UTF-8") ?></h1>
                <p class="lead text-white-50 mb-0">Atención rápida de clientes, consulta de stock en cámara y registro ágil de ventas.</p>
            </div>
        </section>

        <!-- Navegación Modular por Pestañas -->
        <div class="mb-4">
            <ul class="nav nav-tabs-app" id="vendedorTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-vendedor-resumen-btn" data-bs-toggle="tab" data-bs-target="#pestana-resumen" type="button" role="tab" aria-controls="pestana-resumen" aria-selected="true">
                        <span>Resumen</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-vendedor-productos-btn" data-bs-toggle="tab" data-bs-target="#pestana-productos" type="button" role="tab" aria-controls="pestana-productos" aria-selected="false">
                        <span>Stock en Cámara</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-vendedor-ventas-btn" data-bs-toggle="tab" data-bs-target="#pestana-ventas" type="button" role="tab" aria-controls="pestana-ventas" aria-selected="false">
                        <span>Registro de Ventas</span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Contenido de las Pestañas -->
        <div class="tab-content" id="vendedorTabsContent">
            
            <!-- Pestaña 1: Resumen -->
            <div class="tab-pane fade show active" id="pestana-resumen" role="tabpanel" aria-labelledby="tab-vendedor-resumen-btn">
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-4">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Presentaciones de Hielo</span>
                            <div id="resumenProductos" class="stat-valor">—</div>
                            <small class="text-muted">Tipos disponibles</small>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Bolsas en Cámara</span>
                            <div id="resumenStock" class="stat-valor">—</div>
                            <small class="text-muted">Stock físico disponible</small>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Ventas realizadas</span>
                            <div id="resumenVentas" class="stat-valor">—</div>
                            <small class="text-muted">Total operaciones</small>
                        </div>
                    </div>
                </div>

                <div class="seccion-card">
                    <h2 class="h5 fw-bold mb-3">Accesos Directos</h2>
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <a class="btn btn-outline-success w-100 p-3 text-start d-flex align-items-center gap-3" href="venta_form.php">
                                <span class="fs-3">⚡</span>
                                <div>
                                    <div class="fw-bold">Punto de Venta (POS)</div>
                                    <small class="text-muted">Carga rápida de ticket con cliente</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-12 col-sm-6">
                            <a class="btn btn-outline-info w-100 p-3 text-start d-flex align-items-center gap-3" href="catalogo.php">
                                <span class="fs-3">❄️</span>
                                <div>
                                    <div class="fw-bold">Catálogo Visual de Hielo</div>
                                    <small class="text-muted">Ver imágenes y precios de venta</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña 2: Inventario -->
            <div class="tab-pane fade" id="pestana-productos" role="tabpanel" aria-labelledby="tab-vendedor-productos-btn">
                <section class="seccion-card" aria-labelledby="titulo-productos">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                        <div>
                            <p class="etiqueta text-primary mb-1">Cámara de Frío</p>
                            <h2 id="titulo-productos" class="h4 fw-bold mb-0">Presentaciones de Hielo Disponibles</h2>
                        </div>
                        <button class="btn btn-outline-primary" type="button" id="btnEscanearProductoTabla" title="Buscar con lector o cámara">Escanear código</button>
                    </div>

                    <!-- Filtros de Inventario -->
                    <form id="formFiltrosProductos" class="row g-3 align-items-end mb-3 p-3 bg-light rounded-3 border" onsubmit="return false;">
                        <div class="col-12 col-sm-7 col-lg-6">
                            <label for="filtroProductoBusqueda" class="form-label">Buscar presentación / código</label>
                            <div class="input-group">
                                <input type="text" id="filtroProductoBusqueda" name="busqueda" class="form-control" placeholder="Nombre, código de barras...">
                                <button class="btn btn-outline-secondary" type="button" id="btnEscanearFiltro" title="Escanear con cámara">Escanear</button>
                            </div>
                        </div>
                        <div class="col-12 col-sm-5 col-lg-4">
                            <label for="filtroProductoPresentacion" class="form-label">Empaque</label>
                            <select id="filtroProductoPresentacion" name="presentacion" class="form-select">
                                <option value="">Todas las presentaciones</option>
                                <option value="unidad">Bolsa individual (Unidad)</option>
                                <option value="caja">Caja</option>
                                <option value="bulto">Bulto / Pack cerrado</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-2">
                            <button type="button" id="limpiarFiltrosProductos" class="btn btn-outline-secondary w-100">Limpiar</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Cód. / Barras</th>
                                    <th>Presentación / Producto</th>
                                    <th>Empaque</th>
                                    <th>Precio Unitario</th>
                                    <th>Stock en Cámara</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="productosBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Pestaña 3: Ventas -->
            <div class="tab-pane fade" id="pestana-ventas" role="tabpanel" aria-labelledby="tab-vendedor-ventas-btn">
                <section class="seccion-card" aria-labelledby="titulo-ventas">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                        <div>
                            <p class="etiqueta text-primary mb-1">Actividad Comercial</p>
                            <h2 id="titulo-ventas" class="h4 fw-bold mb-0">Historial de Ventas</h2>
                        </div>
                        <a class="btn btn-primary" href="venta_form.php">+ Punto de Venta (POS)</a>
                    </div>

                    <!-- Filtros -->
                    <form id="formFiltrosVentas" class="row g-3 align-items-end mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroDesde" class="form-label">Desde</label>
                            <input type="date" id="filtroDesde" name="desde" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroHasta" class="form-label">Hasta</label>
                            <input type="date" id="filtroHasta" name="hasta" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroProducto" class="form-label">Presentación de Hielo</label>
                            <select id="filtroProducto" name="producto_id" class="form-select">
                                <option value="">Todas las presentaciones</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroEstado" class="form-label">Estado</label>
                            <select id="filtroEstado" name="estado" class="form-select">
                                <option value="">Todos los estados</option>
                                <option value="ACTIVA">Activa</option>
                                <option value="ENTREGADO">Entregado</option>
                                <option value="MODIFICADA">Modificada</option>
                                <option value="CANCELADA">Cancelada</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <button type="button" id="limpiarFiltros" class="btn btn-outline-secondary">Limpiar filtros</button>
                            <button type="button" id="btnExportarVentasCsv" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Exportar Listado Filtrado (CSV)</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Presentación</th>
                                    <th>Cantidad</th>
                                    <th>Precio unit.</th>
                                    <th>Descuento</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="ventasBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>

        </div>
    </main>

    <!-- Modal Historial de Movimientos de Producto -->
    <div class="modal fade" id="modalHistorialProducto" tabindex="-1" aria-labelledby="tituloModalHistorialProducto" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <p class="etiqueta text-primary mb-1">Auditoría y Trazabilidad</p>
                        <h2 id="tituloModalHistorialProducto" class="modal-title fs-5 fw-bold">Historial de Movimientos</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-sm-6">
                                <h3 class="h6 fw-bold mb-1" id="historialProductoNombre">—</h3>
                                <div class="text-muted small" id="historialProductoDetalles">Cód: — | Barras: —</div>
                            </div>
                            <div class="col-6 col-sm-3 text-sm-center">
                                <span class="text-muted small d-block">Stock en Cámara</span>
                                <strong class="fs-5 text-primary" id="historialProductoStock">—</strong>
                            </div>
                            <div class="col-6 col-sm-3 text-sm-end">
                                <span class="text-muted small d-block">Precio Vigente</span>
                                <strong class="fs-5 text-success" id="historialProductoPrecio">—</strong>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha y Hora</th>
                                    <th>Tipo de Movimiento</th>
                                    <th>Detalle / Operación</th>
                                    <th>Variación Stock</th>
                                    <th>Responsable</th>
                                </tr>
                            </thead>
                            <tbody id="historialProductoBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Escáner de Cámara Web / Móvil -->
    <div class="modal fade" id="modalScannerCamara" tabindex="-1" aria-labelledby="tituloModalScanner" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="tituloModalScanner" class="modal-title fs-5 fw-bold">Escanear Código de Barras</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="text-muted small mb-3">Apuntá con la cámara hacia el código de barras de la bolsa de hielo.</p>
                    <div id="contenedorLectorCamara" class="p-2 mb-3">
                        <div id="qr-reader"></div>
                    </div>
                    <div id="scannerResultado" class="alert alert-info d-none mb-0 py-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="btnCerrarScanner">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Modificar Venta -->
    <div class="modal fade" id="modalModificarVenta" tabindex="-1" aria-labelledby="tituloModificarVenta" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formModificarVenta">
                    <div class="modal-header">
                        <h2 id="tituloModificarVenta" class="modal-title fs-5 fw-bold">Modificar cantidad de venta</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorModificarVenta" class="alert alert-danger d-none"></div>
                        <input type="hidden" name="venta_id" id="modificarVentaId">
                        <input type="hidden" name="accion" value="modificar_cantidad">
                        <label for="modificarCantidad" class="form-label">Nueva cantidad</label>
                        <input type="number" min="1" id="modificarCantidad" name="cantidad" class="form-control" required>
                        <label for="motivoModificacion" class="form-label mt-3">Motivo (opcional)</label>
                        <textarea id="motivoModificacion" name="motivo" class="form-control" maxlength="500" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                        <button type="submit" class="btn btn-primary">Guardar cambio</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Cancelar Venta -->
    <div class="modal fade" id="modalCancelarVenta" tabindex="-1" aria-labelledby="tituloCancelarVenta" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formCancelarVenta">
                    <div class="modal-header">
                        <h2 id="tituloCancelarVenta" class="modal-title fs-5 fw-bold">Cancelar venta</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorCancelarVenta" class="alert alert-danger d-none"></div>
                        <input type="hidden" name="venta_id" id="cancelarVentaId">
                        <input type="hidden" name="accion" value="cancelar">
                        <p class="texto-secundario">La venta permanecerá en el historial como CANCELADA y el stock será devuelto automáticamente a la cámara de frío.</p>
                        <label for="motivoCancelacion" class="form-label">Motivo (opcional)</label>
                        <textarea id="motivoCancelacion" name="motivo" class="form-control" maxlength="500" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                        <button type="submit" class="btn btn-danger">Confirmar cancelación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/panel.js"></script>
</body>
</html>
