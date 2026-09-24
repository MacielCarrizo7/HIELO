<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin"]);
require_once __DIR__ . "/FirestoreConexion.php";

try {
    $cantidadUsuarios = FirestoreConexion::obtenerFirestore()->contarDocumentos("usuarios");
} catch (Throwable $e) {
    error_log("Error al contar usuarios en Firestore: " . $e->getMessage());
    $cantidadUsuarios = 0;
}
$nombreCompleto = trim(($_SESSION["usuario_nombre"] ?? "Administrador") . " " . ($_SESSION["usuario_apellido"] ?? ""));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content">
    <title>Panel Administrativo | Control Stock Hielo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
    <!-- Librerías para Códigos de Barra, Escáner y Códigos QR -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body data-rol="admin" data-csrf="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, "UTF-8") ?>" data-limite-descuento="<?= htmlspecialchars((string)($_SESSION['usuario_limite_descuento'] ?? 100), ENT_QUOTES, 'UTF-8') ?>">
    <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="admin.php">
                <span class="marca-icono" aria-hidden="true">❄️</span>
                <span class="fw-bold">Control Stock Hielo</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuAdmin" aria-controls="menuAdmin" aria-expanded="false" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuAdmin">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-1 pt-3 pt-lg-0">
                    <a class="nav-link nav-link-app active" href="admin.php">Panel Principal</a>
                    <a class="nav-link nav-link-app" href="catalogo.php">Catálogo Visual</a>
                    <a class="nav-link nav-link-app" href="categorias.php">Categorías</a>
                    <a class="nav-link nav-link-app" href="registro.php">Usuarios</a>
                    <button class="btn btn-outline-primary btn-sm ms-lg-2" type="button" id="btnAbrirScannerGlobal" title="Escanear código de barras con cámara">Escanear</button>
                    <a class="btn btn-outline-primary btn-sm ms-lg-1" href="venta_form.php">Punto de Venta</a>
                    <a class="btn btn-primary btn-sm ms-lg-1" href="producto_form.php">+ Ingreso de Hielo</a>
                    <a class="btn btn-outline-danger btn-sm ms-lg-2" href="logout.php">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-md-5">
        <?php if (isset($_GET["mfa_configurado"])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="status">
                El autenticador de segundo factor se configuró correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- Encabezado Principal -->
        <section class="hero-panel p-4 p-md-5 mb-4">
            <div class="hero-contenido">
                <p class="etiqueta text-white-50 mb-2">Fábrica y Distribución de Hielo</p>
                <h1 class="display-6 fw-bold mb-2">Hola, <?= htmlspecialchars($nombreCompleto, ENT_QUOTES, "UTF-8") ?></h1>
                <p class="lead text-white-50 mb-0">Control de producción en cámara de frío, stock por presentación, ventas y auditoría de mermas.</p>
            </div>
        </section>

        <!-- Navegación Modular por Pestañas -->
        <div class="mb-4">
            <ul class="nav nav-tabs-app" id="adminTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-resumen-btn" data-bs-toggle="tab" data-bs-target="#pestana-resumen" type="button" role="tab" aria-controls="pestana-resumen" aria-selected="true">
                        <span>Resumen General</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-productos-btn" data-bs-toggle="tab" data-bs-target="#pestana-productos" type="button" role="tab" aria-controls="pestana-productos" aria-selected="false">
                        <span>Inventario y Stock</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-ventas-btn" data-bs-toggle="tab" data-bs-target="#pestana-ventas" type="button" role="tab" aria-controls="pestana-ventas" aria-selected="false">
                        <span>Registro de Ventas</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-ingresos-btn" data-bs-toggle="tab" data-bs-target="#pestana-ingresos" type="button" role="tab" aria-controls="pestana-ingresos" aria-selected="false">
                        <span>Kardex de Ingresos</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-bajas-btn" data-bs-toggle="tab" data-bs-target="#pestana-bajas" type="button" role="tab" aria-controls="pestana-bajas" aria-selected="false">
                        <span>Bajas y Mermas</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-barcodes-btn" data-bs-toggle="tab" data-bs-target="#pestana-barcodes" type="button" role="tab" aria-controls="pestana-barcodes" aria-selected="false">
                        <span>Códigos de Barra</span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Contenido de las Pestañas -->
        <div class="tab-content" id="adminTabsContent">
            
            <!-- Pestaña 1: Resumen General -->
            <div class="tab-pane fade show active" id="pestana-resumen" role="tabpanel" aria-labelledby="tab-resumen-btn">
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-4">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Presentaciones activas</span>
                            <div id="resumenProductos" class="stat-valor">—</div>
                            <small class="text-muted">Bolsas y tipos de hielo</small>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Bolsas en Cámara de Frío</span>
                            <div id="resumenStock" class="stat-valor">—</div>
                            <small class="text-muted">Unidades disponibles</small>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Ventas realizadas</span>
                            <div id="resumenVentas" class="stat-valor">—</div>
                            <small class="text-muted">Historial comercial</small>
                        </div>
                    </div>
                </div>

                <!-- Bandeja de Solicitudes de Atención de Clientes -->
                <section class="seccion-card mb-4" aria-labelledby="titulo-solicitudes">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <h2 id="titulo-solicitudes" class="h5 fw-bold mb-0">Solicitudes de Atención de Clientes</h2>
                            <span id="badgeSolicitudesPendientes" class="badge rounded-pill text-bg-danger">0</span>
                        </div>
                        <small class="text-muted">Clientes que solicitaron asistencia directa</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Mensaje / Consulta</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="solicitudesAtencionBody"></tbody>
                        </table>
                    </div>
                </section>

                <div class="seccion-card">
                    <h2 class="h5 fw-bold mb-3">Accesos y Acciones Rápidas</h2>
                    <p class="texto-secundario small mb-4">Operaciones clave del sistema de gestión de hielo.</p>
                    <div class="row g-3">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <a class="btn btn-outline-primary w-100 p-3 text-start d-flex align-items-center gap-3 h-100" href="producto_form.php">
                                <span class="fs-3">🧊</span>
                                <div>
                                    <div class="fw-bold">Ingreso de Hielo</div>
                                    <small class="text-muted">Carga individual o por lote a cámara</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <a class="btn btn-outline-success w-100 p-3 text-start d-flex align-items-center gap-3 h-100" href="venta_form.php">
                                <span class="fs-3">⚡</span>
                                <div>
                                    <div class="fw-bold">Punto de Venta (POS)</div>
                                    <small class="text-muted">Venta rápida con datos de cliente</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <a class="btn btn-outline-info w-100 p-3 text-start d-flex align-items-center gap-3 h-100" href="catalogo.php">
                                <span class="fs-3">❄️</span>
                                <div>
                                    <div class="fw-bold">Catálogo Visual</div>
                                    <small class="text-muted">Muestrario de presentaciones</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <a class="btn btn-outline-warning w-100 p-3 text-start d-flex align-items-center gap-3 h-100" href="registro.php">
                                <span class="fs-3">👥</span>
                                <div>
                                    <div class="fw-bold">Usuarios y Permisos</div>
                                    <small class="text-muted">Vendedores y Administradores</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña 2: Inventario de Productos -->
            <div class="tab-pane fade" id="pestana-productos" role="tabpanel" aria-labelledby="tab-productos-btn">
                <section class="seccion-card" aria-labelledby="titulo-productos">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                        <div>
                            <p class="etiqueta text-primary mb-1">Cámara de Frío y Stock</p>
                            <h2 id="titulo-productos" class="h4 fw-bold mb-0">Inventario de Presentaciones de Hielo</h2>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-primary" type="button" id="btnEscanearProductoTabla" title="Buscar con lector de código de barras">Escanear código</button>
                            <a class="btn btn-primary" href="producto_form.php">+ Ingreso de Hielo</a>
                        </div>
                    </div>

                    <!-- Filtros de Inventario -->
                    <form id="formFiltrosProductos" class="row g-3 align-items-end mb-4 p-3 bg-light rounded border" onsubmit="return false;">
                        <div class="col-12 col-sm-7 col-lg-6">
                            <label for="filtroProductoBusqueda" class="form-label">Buscar presentación / código</label>
                            <div class="input-group">
                                <input type="text" id="filtroProductoBusqueda" name="busqueda" class="form-control" placeholder="Ej: Cubos 2kg, Rollo 5kg, código de barras...">
                                <button class="btn btn-outline-secondary" type="button" id="btnEscanearFiltro" title="Escanear con cámara">Escanear</button>
                            </div>
                        </div>
                        <div class="col-12 col-sm-5 col-lg-4">
                            <label for="filtroProductoPresentacion" class="form-label">Presentación / Empaque</label>
                            <select id="filtroProductoPresentacion" name="presentacion" class="form-select">
                                <option value="">Todas las presentaciones</option>
                                <option value="unidad">Bolsas individuales (Unidad)</option>
                                <option value="caja">Cajas</option>
                                <option value="bulto">Bultos / Packs cerrados</option>
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
            <div class="tab-pane fade" id="pestana-ventas" role="tabpanel" aria-labelledby="tab-ventas-btn">
                <section class="seccion-card" aria-labelledby="titulo-ventas">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                        <div>
                            <p class="etiqueta text-primary mb-1">Actividad Comercial</p>
                            <h2 id="titulo-ventas" class="h4 fw-bold mb-0">Historial de Ventas</h2>
                        </div>
                        <a class="btn btn-primary" href="venta_form.php">+ Punto de Venta (POS)</a>
                    </div>

                    <!-- Filtros de Ventas -->
                    <form id="formFiltrosVentas" class="row g-3 align-items-end mb-4 p-3 bg-light rounded border">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroDesde" class="form-label">Desde</label>
                            <input type="date" id="filtroDesde" name="desde" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroHasta" class="form-label">Hasta</label>
                            <input type="date" id="filtroHasta" name="hasta" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroProducto" class="form-label">Presentación</label>
                            <select id="filtroProducto" name="producto_id" class="form-select">
                                <option value="">Todas las presentaciones</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroEstado" class="form-label">Estado</label>
                            <select id="filtroEstado" name="estado" class="form-select">
                                <option value="">Todos los estados</option>
                                <option value="ACTIVA">Activa</option>
                                <option value="MODIFICADA">Modificada</option>
                                <option value="CANCELADA">Cancelada</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <button type="button" id="limpiarFiltros" class="btn btn-outline-secondary">Limpiar filtros</button>
                            <span id="errorFiltros" class="text-danger small align-self-center"></span>
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
                                    <th>Precio Unit.</th>
                                    <th>Descuento</th>
                                    <th>Total</th>
                                    <th>Vendedor</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="ventasBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Pestaña 4: Kardex de Ingresos -->
            <div class="tab-pane fade" id="pestana-ingresos" role="tabpanel" aria-labelledby="tab-ingresos-btn">
                <section class="seccion-card" aria-labelledby="titulo-ingresos">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                        <div>
                            <p class="etiqueta text-primary mb-1">Auditoría de Entradas</p>
                            <h2 id="titulo-ingresos" class="h4 fw-bold mb-0">Kardex de Producción e Ingresos a Cámara</h2>
                        </div>
                        <span class="badge text-bg-primary fs-6 px-3 py-2" id="resumenIngresos">0</span>
                    </div>

                    <form id="formFiltrosIngresos" class="row g-3 align-items-end mb-4 p-3 bg-light rounded border">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroIngresoDesde" class="form-label">Desde</label>
                            <input type="date" id="filtroIngresoDesde" name="desde" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroIngresoHasta" class="form-label">Hasta</label>
                            <input type="date" id="filtroIngresoHasta" name="hasta" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroIngresoProducto" class="form-label">Presentación</label>
                            <select id="filtroIngresoProducto" name="producto_id" class="form-select">
                                <option value="">Todas las presentaciones</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroIngresoFactura" class="form-label">N° Factura / Remito</label>
                            <input type="text" id="filtroIngresoFactura" name="numero_factura" class="form-control" placeholder="Ej: FC-0001...">
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <button type="button" id="limpiarFiltrosIngresos" class="btn btn-outline-secondary">Limpiar filtros</button>
                            <span id="errorFiltrosIngresos" class="text-danger small align-self-center"></span>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>N° Remito / Factura</th>
                                    <th>Presentación</th>
                                    <th>Bolsas / Unidades</th>
                                    <th>Responsable</th>
                                    <th>Motivo / Ajuste</th>
                                </tr>
                            </thead>
                            <tbody id="ingresosBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Pestaña 5: Bajas de Inventario y Merma -->
            <div class="tab-pane fade" id="pestana-bajas" role="tabpanel" aria-labelledby="tab-bajas-btn">
                <section class="seccion-card" aria-labelledby="titulo-bajas">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                        <div>
                            <p class="etiqueta text-danger mb-1">Auditoría y Pérdidas</p>
                            <h2 id="titulo-bajas" class="h4 fw-bold mb-0">Registro de Bajas y Merma de Hielo</h2>
                            <small class="text-muted">Historial inmutable de bolsas descartadas por descongelamiento, rotura de bolsa, falla de cámara o ajuste.</small>
                        </div>
                        <span class="badge text-bg-danger fs-6 px-3 py-2" id="resumenBajas">0 bajas</span>
                    </div>

                    <!-- Filtros de Bajas -->
                    <form id="formFiltrosBajas" class="row g-3 align-items-end mb-4 p-3 bg-light rounded border">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroBajaBusqueda" class="form-label">Buscar presentación / código</label>
                            <input type="text" id="filtroBajaBusqueda" name="q" class="form-control" placeholder="Nombre, código...">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroBajaMotivo" class="form-label">Motivo de Baja</label>
                            <select id="filtroBajaMotivo" name="motivo" class="form-select">
                                <option value="">Todos los motivos</option>
                                <option value="Merma / Descongelamiento">Merma / Descongelamiento</option>
                                <option value="Rotura de empaque">Rotura de empaque</option>
                                <option value="Ajuste de inventario">Ajuste de inventario</option>
                                <option value="Eliminación manual">Eliminación manual</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroBajaDesde" class="form-label">Desde</label>
                            <input type="date" id="filtroBajaDesde" name="desde" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroBajaHasta" class="form-label">Hasta</label>
                            <input type="date" id="filtroBajaHasta" name="hasta" class="form-control">
                        </div>
                        <div class="col-12 col-sm-12 col-lg-2">
                            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted text-uppercase">
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha de Baja</th>
                                    <th>Presentación</th>
                                    <th>Categoría</th>
                                    <th>Motivo de Baja</th>
                                    <th>Stock Remanente</th>
                                    <th>Pérdida Estimada ($)</th>
                                    <th>Responsable</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody id="bajasBody">
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">Cargando registro de bajas de inventario...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Pestaña 6: Generador e Impresión de Códigos de Barra -->
            <div class="tab-pane fade" id="pestana-barcodes" role="tabpanel" aria-labelledby="tab-barcodes-btn">
                <section class="seccion-card" aria-labelledby="titulo-barcodes">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
                        <div>
                            <p class="etiqueta text-primary mb-1">Etiquetado de Bolsas</p>
                            <h2 id="titulo-barcodes" class="h4 fw-bold mb-0">Generador de Códigos de Barra</h2>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-12 col-lg-6">
                            <div class="p-3 bg-light rounded border">
                                <h3 class="h6 fw-bold mb-3">Configurar datos de la etiqueta</h3>
                                
                                <div class="mb-3">
                                    <label class="form-label" for="barcodeSelectorProducto">Seleccionar presentación existente (opcional)</label>
                                    <select class="form-select" id="barcodeSelectorProducto">
                                        <option value="">-- Ingreso manual / Nueva presentación --</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="barcodeInputCodigo">Código de Barras *</label>
                                    <div class="input-group">
                                        <input type="text" id="barcodeInputCodigo" class="form-control font-monospace" placeholder="Ej: 7791234567890">
                                        <button class="btn btn-outline-secondary" type="button" id="btnGenerarCodigoRandom" title="Generar código aleatorio">Generar</button>
                                    </div>
                                    <small class="text-muted">Admite formatos estándar (EAN-13, CODE128, etc.).</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="barcodeInputNombre">Nombre / Descripción en etiqueta</label>
                                    <input type="text" id="barcodeInputNombre" class="form-control" placeholder="Ej: Bolsa de Hielo en Cubos 2kg">
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label" for="barcodeInputPrecio">Precio a mostrar ($)</label>
                                        <input type="number" step="0.01" id="barcodeInputPrecio" class="form-control" placeholder="0.00">
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label" for="barcodeInputFormato">Tipo de código</label>
                                        <select id="barcodeInputFormato" class="form-select">
                                            <option value="CODE128">CODE128 (Universal)</option>
                                            <option value="EAN13">EAN-13 (13 dígitos)</option>
                                            <option value="CODE39">CODE39</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="etiqueta-barcode-card h-100 d-flex flex-column justify-content-center align-items-center">
                                <h3 class="h6 fw-bold text-muted mb-3">Vista Previa de la Etiqueta</h3>
                                
                                <div id="seccionImpresionEtiqueta">
                                    <div class="etiqueta-print-box shadow-sm">
                                        <div class="etiqueta-print-empresa">Fábrica de Hielo</div>
                                        <div class="etiqueta-print-nombre" id="previewEtiquetaNombre">Bolsa de Hielo</div>
                                        <svg id="previewBarcodeSvg" class="barcode-svg my-2"></svg>
                                        <div class="etiqueta-print-precio" id="previewEtiquetaPrecio">$ 0,00</div>
                                    </div>
                                </div>

                                <div class="mt-4 d-flex flex-wrap gap-2 justify-content-center">
                                    <button class="btn btn-outline-dark btn-sm px-3" type="button" id="btnImprimirEtiqueta">Imprimir Etiqueta</button>
                                    <button class="btn btn-primary btn-sm px-3 fw-bold" type="button" id="btnGuardarCodigoHistorial">Guardar en Historial</button>
                                    <button class="btn btn-outline-secondary btn-sm px-3" type="button" id="btnCopiarCodigoBarras">Copiar código</button>
                                </div>

                                <div class="mt-3 text-start small text-muted p-2 bg-light rounded border w-100" style="max-width: 440px; font-size: 0.8rem; line-height: 1.45;">
                                    <div class="mb-1"><strong>Imprimir Etiqueta:</strong> Envía el código a la impresora térmica o estándar.</div>
                                    <div class="mb-1"><strong>Guardar en Historial:</strong> Registra la etiqueta para uso frecuente y consulta posterior.</div>
                                    <div><strong>Copiar código:</strong> Copia los dígitos al portapapeles.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla Historial de Códigos de Barra Creados -->
                    <div class="mt-4 pt-4 border-top">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                            <div>
                                <p class="etiqueta text-primary mb-1">Registro y Trazabilidad</p>
                                <h3 class="h5 fw-bold mb-0">Historial de Códigos Creados</h3>
                                <small class="text-muted">Reutilizá, visualizá o reimprimí códigos generados anteriormente.</small>
                            </div>
                            <div class="col-12 col-sm-4">
                                <input type="text" id="buscadorHistorialCodigos" class="form-control form-control-sm" placeholder="Buscar código o producto...">
                            </div>
                        </div>

                        <div class="table-responsive bg-white border rounded">
                            <table class="table table-hover align-middle mb-0" id="tablaHistorialCodigos">
                                <thead class="table-light small text-muted text-uppercase">
                                    <tr>
                                        <th>#</th>
                                        <th>Fecha Creación</th>
                                        <th>Código de Barras</th>
                                        <th>Presentación / Detalle</th>
                                        <th>Precio ($)</th>
                                        <th>Formato</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="historialCodigosBody">
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            Cargando historial de códigos...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

        </div>
    </main>

    <!-- Modal Confirmar Baja de Producto (Auditoría de Bajas y Merma) -->
    <div class="modal fade" id="modalConfirmarBaja" tabindex="-1" aria-labelledby="tituloModalBaja" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formConfirmarBaja">
                    <input type="hidden" name="id" id="bajaProductoId">
                    <div class="modal-header">
                        <h2 id="tituloModalBaja" class="modal-title fs-5 fw-bold text-danger">Baja / Merma de Hielo</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorBajaProducto" class="alert alert-danger d-none"></div>

                        <p class="text-muted small mb-3">La presentación será retirada del stock activo y transferida permanentemente al registro de <strong>Bajas y Mermas</strong>.</p>

                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="fw-bold text-dark" id="bajaProductoNombre">—</div>
                            <div class="small text-muted" id="bajaProductoDetalles">Stock remanente: 0 un.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="bajaMotivo">Motivo de la Baja / Pérdida *</label>
                            <select class="form-select" id="bajaMotivo" name="motivo" required>
                                <option value="Merma / Descongelamiento">Merma / Descongelamiento / Falta de frío</option>
                                <option value="Rotura de empaque">Rotura de bolsa / empaque</option>
                                <option value="Ajuste de inventario">Ajuste de inventario</option>
                                <option value="Eliminación manual">Eliminación manual</option>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label" for="bajaObservaciones">Observaciones adicionales (opcional)</label>
                            <textarea class="form-control" id="bajaObservaciones" name="observaciones" rows="2" placeholder="Detalles de la merma o rotura..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger" id="btnEjecutarBaja">Confirmar Baja</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Historial de Movimientos de Producto (Auditoría / Kardex) -->
    <div class="modal fade" id="modalHistorialProducto" tabindex="-1" aria-labelledby="tituloModalHistorialProducto" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <p class="etiqueta text-primary mb-1">Auditoría de Cámara</p>
                        <h2 id="tituloModalHistorialProducto" class="modal-title fs-5 fw-bold">Historial de Movimientos de la Presentación</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <!-- Resumen del Producto Consultado -->
                    <div class="p-3 bg-light rounded border mb-4">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-sm-6">
                                <h3 class="h6 fw-bold mb-1" id="historialProductoNombre">—</h3>
                                <div class="text-muted small" id="historialProductoDetalles">Cód: — | Barras: —</div>
                            </div>
                            <div class="col-6 col-sm-3 text-sm-center">
                                <span class="text-muted small d-block">Stock Actual</span>
                                <strong class="fs-5 text-primary" id="historialProductoStock">—</strong>
                            </div>
                            <div class="col-6 col-sm-3 text-sm-end">
                                <span class="text-muted small d-block">Precio Vigente</span>
                                <strong class="fs-5 text-success" id="historialProductoPrecio">—</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Movimientos Cronológicos -->
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
                    <p class="text-muted small mb-3">Apuntá con la cámara hacia el código de barras de la bolsa para detectarlo automáticamente.</p>
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

    <!-- Modal Modificar / Editar Producto -->
    <div class="modal fade" id="modalEditarProducto" tabindex="-1" aria-labelledby="tituloModalEditarProducto" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formEditarProducto">
                    <input type="hidden" name="id" id="editarProductoId">
                    <div class="modal-header">
                        <h2 id="tituloModalEditarProducto" class="modal-title fs-5 fw-bold">Modificar Presentación</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorEditarProducto" class="alert alert-danger d-none"></div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="editarProductoNombre">Nombre / Presentación *</label>
                            <input class="form-control" id="editarProductoNombre" name="nombre" maxlength="150" placeholder="Ej: Bolsa de Hielo en Cubos 2kg" required>
                        </div>

                        <!-- Código de Barras -->
                        <div class="mb-3">
                            <label class="form-label" for="editarProductoCodigoBarras">Código de Barras</label>
                            <div class="input-group">
                                <input class="form-control font-monospace" id="editarProductoCodigoBarras" name="codigo_barras" maxlength="50" placeholder="Ej: 7791234567890">
                                <button class="btn btn-outline-secondary" type="button" id="btnEscanearCodigoModalEdicion" title="Escanear con cámara">Escanear</button>
                                <button class="btn btn-outline-secondary" type="button" id="btnGenerarCodigoModalEdicion" title="Generar código aleatorio">Generar</button>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="editarProductoPrecio">Precio unitario ($) *</label>
                                <input class="form-control" id="editarProductoPrecio" name="precio" type="number" min="0.01" step="0.01" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="editarProductoPresentacion">Empaque *</label>
                                <select class="form-select" id="editarProductoPresentacion" name="presentacion" required>
                                    <option value="unidad">Bolsa suelta (Unidad)</option>
                                    <option value="caja">Caja</option>
                                    <option value="bulto">Bulto / Pack cerrado</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6 d-none" id="contenedorEditarUnidadesBulto">
                                <label class="form-label" for="editarProductoUnidadesBulto">Bolsas por empaque *</label>
                                <input class="form-control" id="editarProductoUnidadesBulto" name="unidades_por_bulto" type="number" min="1" value="1">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="editarProductoStock">Stock en Cámara (bolsas) *</label>
                                <input class="form-control" id="editarProductoStock" name="stock" type="number" min="0" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="editarProductoMotivo">Motivo del ajuste (opcional)</label>
                            <input class="form-control" id="editarProductoMotivo" name="motivo" maxlength="255" placeholder="Ej: Ajuste de producción / recuento">
                        </div>

                        <div class="mb-2">
                            <label class="form-label" for="editarProductoNumeroFactura">N° Remito / Factura (si ingresa nuevo stock)</label>
                            <input class="form-control" id="editarProductoNumeroFactura" name="numero_factura" maxlength="60" placeholder="Ej: REM-0001-00023456">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Modificar Cantidad de Venta -->
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
