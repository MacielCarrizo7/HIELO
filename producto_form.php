<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin"]);
require_once __DIR__ . "/FirestoreConexion.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$esEdicion = ($id > 0);
$producto = null;

$firestore = FirestoreConexion::obtenerFirestore();

if ($esEdicion) {
    $producto = $firestore->obtenerDocumento("productos", (string)$id);
    if (!$producto) {
        header("Location: admin.php?error=" . urlencode("La presentación de hielo no existe."));
        exit;
    }
}

// Cargar productos existentes para obtener stock y datos de las bolsas de 3 kg y 1.5 kg
$todosLosProductos = $firestore->obtenerColeccion("productos");
$producto3kg = null;
$producto1_5kg = null;

foreach ($todosLosProductos as $p) {
    $pNom = mb_strtolower((string)($p["nombre"] ?? ""));
    if (!$producto3kg && (str_contains($pNom, "3 kg") || str_contains($pNom, "3kg") || str_contains($pNom, "3 k"))) {
        $producto3kg = $p;
    }
    if (!$producto1_5kg && (str_contains($pNom, "1.5 kg") || str_contains($pNom, "1.5kg") || str_contains($pNom, "1,5 kg") || str_contains($pNom, "1,5kg") || str_contains($pNom, "1.5k"))) {
        $producto1_5kg = $p;
    }
}

// Cargar categorías
$categorias = $firestore->obtenerColeccion("categorias");
usort($categorias, fn($a, $b) => strcasecmp($a["nombre"] ?? "", $b["nombre"] ?? ""));

$nombreCompleto = trim(($_SESSION["usuario_nombre"] ?? "Administrador") . " " . ($_SESSION["usuario_apellido"] ?? ""));
$csrf = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= $esEdicion ? "Editar Presentación #{$id}" : "Ingreso de Producción a Cámara" ?> | Control Stock Hielo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="assets/estilos.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        .card-produccion-hielo {
            background: #ffffff;
            border: 2px solid #bae6fd;
            border-radius: 18px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.06);
        }
        .card-produccion-hielo:hover {
            border-color: #0284c7;
            box-shadow: 0 8px 25px rgba(2, 132, 199, 0.12);
        }
        .icono-bolsa-hielo {
            width: 58px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: linear-gradient(135deg, #0284c7, #38bdf8);
            color: #ffffff;
            font-size: 1.8rem;
        }
        .qty-prod-stepper {
            height: 48px;
            font-size: 1.35rem;
            font-weight: 800;
            text-align: center;
            border-radius: 10px;
        }
        .btn-stepper-prod {
            width: 48px;
            height: 48px;
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
        .btn-quick-add {
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 4px 10px;
        }
    </style>
</head>
<body data-rol="admin" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, "UTF-8") ?>">
    <!-- Barra Superior -->
    <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="admin.php">
                <span class="marca-icono" aria-hidden="true">❄️</span>
                <span class="fw-bold">Control Stock Hielo</span>
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="admin.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> <span>Volver al Panel</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="container py-3 py-md-4">
        <?php if ($esEdicion): ?>
        <!-- ============================================================== -->
        <!-- MODO EDICIÓN INDIVIDUAL DE PRESENTACIÓN EXISTENTE              -->
        <!-- ============================================================== -->
        <div class="row justify-content-center">
            <div class="col-12 col-lg-9">
                <!-- Encabezado -->
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                    <div>
                        <a href="admin.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Volver a Inventario</a>
                        <h1 class="h3 fw-bold mt-1 mb-0">Editar Presentación #<?= $id ?></h1>
                        <p class="text-muted small mb-0">Modificación de precios, empaque, código y stock en cámara de frío.</p>
                    </div>
                </div>

                <!-- Alertas -->
                <div id="alertaError" class="alert alert-danger d-none mb-3" role="alert"></div>
                <div id="alertaExito" class="alert alert-success d-none mb-3" role="alert"></div>

                <form id="formProductoStandalone" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $id ?>">

                    <!-- 1. Información General -->
                    <div class="seccion-card mb-4">
                        <h2 class="h5 fw-bold text-primary mb-3">1. Datos de la Presentación</h2>
                        
                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <label for="prodNombre" class="form-label fw-bold">Nombre / Presentación de Hielo *</label>
                                <input type="text" class="form-control" id="prodNombre" name="nombre" value="<?= htmlspecialchars($producto['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: Bolsa de Hielo 3 kg" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="prodCategoria" class="form-label fw-bold">Categoría *</label>
                                <select class="form-select" id="prodCategoria" name="categoria_id" required>
                                    <option value="">-- Seleccionar Categoría --</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <?php 
                                            $sel = (isset($producto['categoria_id']) && (int)$producto['categoria_id'] === (int)$cat['id']) || 
                                                   (isset($producto['categoria_nombre']) && $producto['categoria_nombre'] === $cat['nombre']) ||
                                                   (isset($producto['categoria']) && $producto['categoria'] === $cat['nombre']);
                                        ?>
                                        <option value="<?= $cat['id'] ?>" data-nombre="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>" <?= $sel ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="prodCategoriaNombre" name="categoria_nombre" value="<?= htmlspecialchars($producto['categoria_nombre'] ?? $producto['categoria'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="col-12">
                                <label for="prodDescripcion" class="form-label">Descripción Comercial</label>
                                <textarea class="form-control" id="prodDescripcion" name="descripcion" rows="2" placeholder="Ej: Bolsa de hielo en cubos macizos de agua purificada."><?= htmlspecialchars($producto['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Código de Barras -->
                    <div class="seccion-card mb-4">
                        <h2 class="h5 fw-bold text-primary mb-3">2. Identificación y Código de Barras</h2>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="prodCodigoBarras" class="form-label">Código de Barras de la Bolsa (EAN-13)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace" id="prodCodigoBarras" name="codigo_barras" value="<?= htmlspecialchars($producto['codigo_barras'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: 2001234567890">
                                    <button class="btn btn-outline-secondary" type="button" id="btnEscanearCb" title="Escanear con cámara">Escanear</button>
                                    <button class="btn btn-outline-secondary" type="button" id="btnGenerarCb" title="Generar código aleatorio">Generar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Precios -->
                    <div class="seccion-card mb-4">
                        <h2 class="h5 fw-bold text-primary mb-3">3. Precios y Finanzas</h2>
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="row g-3">
                                <div class="col-12 col-md-5">
                                    <label for="prodPrecioCosto" class="form-label fw-bold">Costo de Fabricación / Bolsa ($) *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control fw-bold" id="prodPrecioCosto" name="precio_costo" value="<?= htmlspecialchars((string)($producto['precio_costo'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                </div>

                                <div class="col-12 col-md-5">
                                    <label for="prodPrecioVenta" class="form-label fw-bold text-success">Precio de Venta ($) *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0.01" class="form-control fw-bold text-success fs-5" id="prodPrecioVenta" name="precio_venta" value="<?= htmlspecialchars((string)($producto['precio_venta'] ?? $producto['precio'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                </div>

                                <div class="col-12 col-md-2 d-flex flex-column justify-content-center text-center">
                                    <span class="small text-muted">Margen Bruto</span>
                                    <span id="lblMargenBruto" class="fw-bold fs-5 text-primary">0%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Stock -->
                    <div class="seccion-card mb-4">
                        <h2 class="h5 fw-bold text-primary mb-3">4. Stock en Cámara</h2>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="prodStock" class="form-label fw-bold">Stock Actual en Cámara (Bolsas) *</label>
                                <input type="number" min="0" class="form-control form-control-lg fw-bold" id="prodStock" name="stock" value="<?= htmlspecialchars((string)($producto['stock'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="prodMotivo" class="form-label small text-muted">Motivo del Ajuste (para auditoría)</label>
                                <input type="text" class="form-control" id="prodMotivo" name="motivo" value="Ajuste de inventario en cámara">
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="d-flex align-items-center justify-content-between p-3 bg-white rounded-3 border shadow-sm flex-wrap gap-2 sticky-bottom mb-5">
                        <a href="admin.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 py-2 fs-6 fw-bold" id="btnGuardarProducto">
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- ============================================================== -->
        <!-- MODO UNIFICADO DE PRODUCCIÓN DE HIELO: 3 KG Y 1.5 KG           -->
        <!-- ============================================================== -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <a href="admin.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Volver al Inventario</a>
                <h1 class="h3 fw-bold mt-1 mb-0">🧊 Ingreso Rápido de Producción de Hielo</h1>
                <p class="text-muted small mb-0">Registro simplificado y directo a cámara de frío para las <strong>bolsas de 3 kg y 1.5 kg</strong>.</p>
            </div>
        </div>

        <!-- Alertas -->
        <div id="alertaError" class="alert alert-danger d-none mb-3" role="alert"></div>
        <div id="alertaExito" class="alert alert-success d-none mb-3" role="alert"></div>

        <form id="formProduccionUnificada">
            <!-- 1. Comprobante / Datos de la Producción -->
            <div class="seccion-card mb-4">
                <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-file-earmark-text me-1"></i> 1. Comprobante y Turno de Fabricación</h2>
                <div class="p-3 bg-light rounded-3 border">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold" for="prodLote">N° Remito / Lote de Producción</label>
                            <input type="text" class="form-control" id="prodLote" name="lote" value="PROD-<?= date('Ymd-His') ?>" placeholder="Ej: PROD-2026-001 o REM-00123">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold" for="prodMotivoIngreso">Turno / Motivo de Producción</label>
                            <input type="text" class="form-control" id="prodMotivoIngreso" name="motivo" value="Producción Diaria de Fábrica" placeholder="Ej: Turno Mañana / Reposición de Cámara">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Tarjetas Directas de Producción: 3 KG y 1.5 KG -->
            <div class="seccion-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h5 fw-bold text-primary mb-0"><i class="bi bi-box-seam me-1"></i> 2. Cantidad de Bolsas Producidas</h2>
                        <small class="text-muted">Ingresá cuántas bolsas se fabricaron hoy para sumarlas automáticamente al stock.</small>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- PRODUCTO 1: BOLSA DE 3 KG -->
                    <div class="col-12 col-lg-6">
                        <div class="card-produccion-hielo p-3 p-md-4 h-100">
                            <div class="d-flex align-items-center gap-3 mb-3 pb-2 border-bottom">
                                <div class="icono-bolsa-hielo">🧊</div>
                                <div>
                                    <span class="badge bg-primary text-white fw-bold mb-1">PRESENTACIÓN PRINCIPAL</span>
                                    <h3 class="h5 fw-bold text-dark mb-0">Bolsa de Hielo 3 kg</h3>
                                    <small class="text-muted">
                                        Stock actual en cámara: <strong class="text-primary fs-6" id="stockActual3kg"><?= (int)($producto3kg['stock'] ?? 0) ?></strong> bolsas
                                    </small>
                                </div>
                            </div>

                            <input type="hidden" name="prod_id_3kg" value="<?= (int)($producto3kg['id'] ?? 0) ?>">
                            <input type="hidden" name="prod_nombre_3kg" value="<?= htmlspecialchars($producto3kg['nombre'] ?? 'Bolsa de Hielo 3 kg', ENT_QUOTES, 'UTF-8') ?>">

                            <!-- Cantidad Producida -->
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">Bolsas Producidas a Sumar *</label>
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary btn-stepper-prod" onclick="modificarCantidadProd('cant3kg', -10)">-10</button>
                                    <button type="button" class="btn btn-outline-secondary btn-stepper-prod" onclick="modificarCantidadProd('cant3kg', -1)">-</button>
                                    <input type="number" min="0" class="form-control qty-prod-stepper text-primary" id="cant3kg" name="cant_3kg" value="0" placeholder="0">
                                    <button type="button" class="btn btn-outline-secondary btn-stepper-prod" onclick="modificarCantidadProd('cant3kg', 1)">+</button>
                                    <button type="button" class="btn btn-outline-secondary btn-stepper-prod" onclick="modificarCantidadProd('cant3kg', 10)">+10</button>
                                </div>

                                <!-- Botones de Suma Rápida -->
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-quick-add" onclick="sumarCantidadProd('cant3kg', 25)">+25</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-quick-add" onclick="sumarCantidadProd('cant3kg', 50)">+50</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-quick-add" onclick="sumarCantidadProd('cant3kg', 100)">+100</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-quick-add" onclick="sumarCantidadProd('cant3kg', 200)">+200</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-quick-add" onclick="sumarCantidadProd('cant3kg', 500)">+500</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-quick-add ms-auto" onclick="document.getElementById('cant3kg').value=0; recalcularResumenProduccion();">0</button>
                                </div>
                            </div>

                            <!-- Precios 3kg -->
                            <div class="row g-2 pt-2 border-top">
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1">Costo Unit. ($)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control" id="costo3kg" name="costo_3kg" value="<?= htmlspecialchars((string)($producto3kg['precio_costo'] ?? 450), ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-success fw-bold mb-1">Venta Unit. ($)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control fw-bold text-success" id="venta3kg" name="venta_3kg" value="<?= htmlspecialchars((string)($producto3kg['precio_venta'] ?? $producto3kg['precio'] ?? 1200), ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PRODUCTO 2: BOLSA DE 1.5 KG -->
                    <div class="col-12 col-lg-6">
                        <div class="card-produccion-hielo p-3 p-md-4 h-100">
                            <div class="d-flex align-items-center gap-3 mb-3 pb-2 border-bottom">
                                <div class="icono-bolsa-hielo" style="background: linear-gradient(135deg, #0284c7, #06b6d4);">🧊</div>
                                <div>
                                    <span class="badge bg-info text-dark fw-bold mb-1">PRESENTACIÓN CHICA</span>
                                    <h3 class="h5 fw-bold text-dark mb-0">Bolsa de Hielo 1.5 kg</h3>
                                    <small class="text-muted">
                                        Stock actual en cámara: <strong class="text-info fs-6" id="stockActual1_5kg"><?= (int)($producto1_5kg['stock'] ?? 0) ?></strong> bolsas
                                    </small>
                                </div>
                            </div>

                            <input type="hidden" name="prod_id_1_5kg" value="<?= (int)($producto1_5kg['id'] ?? 0) ?>">
                            <input type="hidden" name="prod_nombre_1_5kg" value="<?= htmlspecialchars($producto1_5kg['nombre'] ?? 'Bolsa de Hielo 1.5 kg', ENT_QUOTES, 'UTF-8') ?>">

                            <!-- Cantidad Producida -->
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">Bolsas Producidas a Sumar *</label>
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary btn-stepper-prod" onclick="modificarCantidadProd('cant1_5kg', -10)">-10</button>
                                    <button type="button" class="btn btn-outline-secondary btn-stepper-prod" onclick="modificarCantidadProd('cant1_5kg', -1)">-</button>
                                    <input type="number" min="0" class="form-control qty-prod-stepper text-info" id="cant1_5kg" name="cant_1_5kg" value="0" placeholder="0">
                                    <button type="button" class="btn btn-outline-secondary btn-stepper-prod" onclick="modificarCantidadProd('cant1_5kg', 1)">+</button>
                                    <button type="button" class="btn btn-outline-secondary btn-stepper-prod" onclick="modificarCantidadProd('cant1_5kg', 10)">+10</button>
                                </div>

                                <!-- Botones de Suma Rápida -->
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-info btn-quick-add" onclick="sumarCantidadProd('cant1_5kg', 25)">+25</button>
                                    <button type="button" class="btn btn-sm btn-outline-info btn-quick-add" onclick="sumarCantidadProd('cant1_5kg', 50)">+50</button>
                                    <button type="button" class="btn btn-sm btn-outline-info btn-quick-add" onclick="sumarCantidadProd('cant1_5kg', 100)">+100</button>
                                    <button type="button" class="btn btn-sm btn-outline-info btn-quick-add" onclick="sumarCantidadProd('cant1_5kg', 200)">+200</button>
                                    <button type="button" class="btn btn-sm btn-outline-info btn-quick-add" onclick="sumarCantidadProd('cant1_5kg', 500)">+500</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-quick-add ms-auto" onclick="document.getElementById('cant1_5kg').value=0; recalcularResumenProduccion();">0</button>
                                </div>
                            </div>

                            <!-- Precios 1.5kg -->
                            <div class="row g-2 pt-2 border-top">
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1">Costo Unit. ($)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control" id="costo1_5kg" name="costo_1_5kg" value="<?= htmlspecialchars((string)($producto1_5kg['precio_costo'] ?? 250), ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-success fw-bold mb-1">Venta Unit. ($)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control fw-bold text-success" id="venta1_5kg" name="venta_1_5kg" value="<?= htmlspecialchars((string)($producto1_5kg['precio_venta'] ?? $producto1_5kg['precio'] ?? 700), ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resumen y Confirmación de Producción -->
            <div class="seccion-card mb-4 bg-light border">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-md-6 text-center text-md-start">
                        <span class="text-muted small d-block">Total de bolsas a ingresar hoy:</span>
                        <strong class="fs-3 text-primary" id="resumenTotalBolsasProduccion">0 bolsas</strong>
                    </div>
                    <div class="col-12 col-md-6 d-flex justify-content-center justify-content-md-end gap-2">
                        <a href="admin.php" class="btn btn-outline-secondary btn-lg px-3">Cancelar</a>
                        <button type="submit" class="btn btn-success btn-lg px-4 py-3 fw-bold fs-5 shadow d-flex align-items-center gap-2" id="btnGuardarProduccion">
                            <i class="bi bi-check-circle-fill fs-4"></i> <span>Registrar Producción a Cámara</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const esEdicion = <?= $esEdicion ? "true" : "false" ?>;
        const csrfToken = document.body.dataset.csrf;
        const formatoMoneda = new Intl.NumberFormat("es-AR", { style: "currency", currency: "ARS" });

        function modificarCantidadProd(inputId, delta) {
            const el = document.getElementById(inputId);
            if (!el) return;
            let val = Math.max(0, (parseInt(el.value) || 0) + delta);
            el.value = val;
            recalcularResumenProduccion();
        }

        function sumarCantidadProd(inputId, add) {
            const el = document.getElementById(inputId);
            if (!el) return;
            let val = Math.max(0, (parseInt(el.value) || 0) + add);
            el.value = val;
            recalcularResumenProduccion();
        }

        function recalcularResumenProduccion() {
            const cant3 = parseInt(document.getElementById("cant3kg")?.value) || 0;
            const cant15 = parseInt(document.getElementById("cant1_5kg")?.value) || 0;
            const total = cant3 + cant15;
            const lbl = document.getElementById("resumenTotalBolsasProduccion");
            if (lbl) {
                lbl.textContent = `${total} bolsa${total !== 1 ? 's' : ''}`;
            }
        }

        if (!esEdicion) {
            document.getElementById("cant3kg")?.addEventListener("input", recalcularResumenProduccion);
            document.getElementById("cant1_5kg")?.addEventListener("input", recalcularResumenProduccion);

            // Manejador de Guardar Producción Unificada
            const formProd = document.getElementById("formProduccionUnificada");
            if (formProd) {
                formProd.addEventListener("submit", async (e) => {
                    e.preventDefault();
                    const alertErr = document.getElementById("alertaError");
                    const alertOk = document.getElementById("alertaExito");
                    const btn = document.getElementById("btnGuardarProduccion");

                    alertErr.classList.add("d-none");
                    alertOk.classList.add("d-none");

                    const cant3 = parseInt(document.getElementById("cant3kg").value) || 0;
                    const cant15 = parseInt(document.getElementById("cant1_5kg").value) || 0;

                    if (cant3 <= 0 && cant15 <= 0) {
                        alertErr.textContent = "Por favor ingresá una cantidad de bolsas producidas (3 kg o 1.5 kg).";
                        alertErr.classList.remove("d-none");
                        window.scrollTo({ top: 0, behavior: "smooth" });
                        return;
                    }

                    const items = [];
                    if (cant3 > 0) {
                        items.push({
                            id: parseInt(document.querySelector("[name='prod_id_3kg']").value) || 0,
                            nombre: "Bolsa de Hielo 3 kg",
                            cantidad: cant3,
                            precio_costo: parseFloat(document.getElementById("costo3kg").value) || 0,
                            precio_venta: parseFloat(document.getElementById("venta3kg").value) || 0
                        });
                    }
                    if (cant15 > 0) {
                        items.push({
                            id: parseInt(document.querySelector("[name='prod_id_1_5kg']").value) || 0,
                            nombre: "Bolsa de Hielo 1.5 kg",
                            cantidad: cant15,
                            precio_costo: parseFloat(document.getElementById("costo1_5kg").value) || 0,
                            precio_venta: parseFloat(document.getElementById("venta1_5kg").value) || 0
                        });
                    }

                    const payload = {
                        lote: document.getElementById("prodLote").value.trim(),
                        motivo: document.getElementById("prodMotivoIngreso").value.trim(),
                        produccion: items
                    };

                    btn.disabled = true;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Guardando producción...';

                    try {
                        const resp = await fetch("guardar_produccion.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-Token": csrfToken
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok) throw new Error(data.error || "No se pudo registrar la producción.");

                        alertOk.innerHTML = `<strong>¡Producción registrada con éxito!</strong> Se sumaron ${data.total_bolsas} bolsas a la cámara de frío. Redirigiendo al panel...`;
                        alertOk.classList.remove("d-none");
                        window.scrollTo({ top: 0, behavior: "smooth" });

                        setTimeout(() => {
                            window.location.href = "admin.php";
                        }, 1200);

                    } catch (err) {
                        alertErr.textContent = err.message;
                        alertErr.classList.remove("d-none");
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        window.scrollTo({ top: 0, behavior: "smooth" });
                    }
                });
            }
        } else {
            // Edición individual
            const form = document.getElementById("formProductoStandalone");
            form?.addEventListener("submit", async (e) => {
                e.preventDefault();
                const alertErr = document.getElementById("alertaError");
                const alertOk = document.getElementById("alertaExito");
                const btnSubmit = document.getElementById("btnGuardarProducto");

                alertErr.classList.add("d-none");
                alertOk.classList.add("d-none");
                btnSubmit.disabled = true;

                try {
                    const formData = new FormData(form);
                    const resp = await fetch("modificar_producto.php", {
                        method: "POST",
                        headers: { "X-CSRF-Token": csrfToken },
                        body: formData
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok) throw new Error(data.error || "No se pudo guardar la presentación.");

                    alertOk.textContent = data.mensaje || "¡Presentación modificada exitosamente!";
                    alertOk.classList.remove("d-none");
                    setTimeout(() => { window.location.href = "admin.php"; }, 1000);
                } catch (err) {
                    alertErr.textContent = err.message;
                    alertErr.classList.remove("d-none");
                    btnSubmit.disabled = false;
                    window.scrollTo({ top: 0, behavior: "smooth" });
                }
            });
        }
    </script>
</body>
</html>
