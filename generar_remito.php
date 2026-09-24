<?php
/**
 * Generador de Remito Oficial de Entrega de Bolsas de Hielo en Formato PDF / Imprimible
 */
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";

requerirPaginaAutenticada(["admin", "vendedor"]);

$ventaId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT) ?: 0;
$ticketId = trim((string)($_GET["ticket_id"] ?? ""));

if ($ventaId <= 0 && $ticketId === "") {
    die("Identificador de venta o ticket inválido.");
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $itemsVenta = [];
    $clienteNombre = "Consumidor Final";
    $clienteTelefono = "";
    $clienteDireccion = "";
    $fechaVenta = "";
    $fechaEntrega = null;
    $vendedorNombre = "Vendedor";
    $estadoVenta = "ACTIVA";
    $idPrincipal = $ventaId;
    $ticketCod = $ticketId;

    if ($ticketId !== "") {
        $todasLasVentas = $firestore->obtenerColeccion("ventas");
        foreach ($todasLasVentas as $v) {
            if (($v["ticket_id"] ?? "") === $ticketId) {
                $itemsVenta[] = $v;
            }
        }
    }

    if (empty($itemsVenta) && $ventaId > 0) {
        $doc = $firestore->obtenerDocumento("ventas", (string)$ventaId);
        if ($doc) {
            $ticketAsoc = (string)($doc["ticket_id"] ?? "");
            if ($ticketAsoc !== "") {
                $ticketCod = $ticketAsoc;
                $todasLasVentas = $firestore->obtenerColeccion("ventas");
                foreach ($todasLasVentas as $v) {
                    if (($v["ticket_id"] ?? "") === $ticketAsoc) {
                        $itemsVenta[] = $v;
                    }
                }
            } else {
                $itemsVenta[] = $doc;
            }
        }
    }

    if (empty($itemsVenta)) {
        die("No se encontró la venta o el comprobante solicitado en la base de datos.");
    }

    // Ordenar ítems por ID
    usort($itemsVenta, fn($a, $b) => (int)($a["id"] ?? 0) <=> (int)($b["id"] ?? 0));

    // Tomar datos de cabecera del primer ítem
    $primerItem = $itemsVenta[0];
    $idPrincipal = (int)($primerItem["id"] ?? $primerItem["_id"] ?? $ventaId);
    $clienteNombre = (string)($primerItem["cliente_nombre"] ?? "Consumidor Final");
    $clienteTelefono = (string)($primerItem["cliente_telefono"] ?? "");
    $clienteDireccion = (string)($primerItem["cliente_direccion"] ?? "");
    $fechaVenta = (string)($primerItem["fecha"] ?? date("Y-m-d H:i:s"));
    $fechaEntrega = $primerItem["fecha_entrega"] ?? null;
    $estadoVenta = (string)($primerItem["estado"] ?? "ACTIVA");
    if ($ticketCod === "") {
        $ticketCod = (string)($primerItem["ticket_id"] ?? "TK-" . str_pad((string)$idPrincipal, 6, "0", STR_PAD_LEFT));
    }

    // Obtener vendedor
    $usuId = (int)($primerItem["usuario_id"] ?? 0);
    if ($usuId > 0) {
        $uDoc = $firestore->obtenerDocumento("usuarios", (string)$usuId);
        if ($uDoc) {
            $vendedorNombre = trim(($uDoc["nombre"] ?? "") . " " . ($uDoc["apellido"] ?? ""));
        }
    }

    // Formatear correlativo de remito
    $nroRemito = "R0001-" . str_pad((string)$idPrincipal, 8, "0", STR_PAD_LEFT);

    // Calcular totales
    $totalBolsasFisicas = 0;
    $subtotalBruto = 0;
    $descuentoTotal = 0;
    $totalFinal = 0;

    foreach ($itemsVenta as $item) {
        $unidades = (int)($item["cantidad"] ?? $item["total_unidades"] ?? 0);
        $sub = (float)($item["subtotal"] ?? 0);
        $descMonto = (float)($item["descuento_monto"] ?? 0);
        $tot = (float)($item["total"] ?? 0);

        $totalBolsasFisicas += $unidades;
        $subtotalBruto += $sub;
        $descuentoTotal += $descMonto;
        $totalFinal += $tot;
    }

} catch (Throwable $e) {
    die("Error al cargar datos del remito: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$csrf = tokenCsrf();
$esEntregado = ($estadoVenta === "ENTREGADO");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remito <?= htmlspecialchars($nroRemito, ENT_QUOTES, 'UTF-8') ?> - Fábrica de Hielo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    
    <!-- Biblioteca para descarga directa a PDF con 1 clic -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            --bs-font-sans-serif: 'Inter', system-ui, -apple-system, sans-serif;
            --bs-primary: #0284c7;
            --bs-primary-dark: #0369a1;
        }

        body {
            font-family: var(--bs-font-sans-serif);
            background-color: #f1f5f9;
            color: #1e293b;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* Barra de herramientas superior */
        .toolbar-remito {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 12px 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Hoja de Remito A4 */
        .remito-wrapper {
            max-width: 860px;
            margin: 24px auto 40px auto;
            background: #ffffff;
            padding: 36px 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .remito-header {
            border-bottom: 2px solid #0284c7;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }

        .tipo-comprobante-box {
            width: 54px;
            height: 54px;
            border: 2px solid #0284c7;
            background: #f0f9ff;
            color: #0284c7;
            font-size: 28px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 4px auto;
            border-radius: 6px;
        }

        .nro-remito-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.15rem;
            font-weight: 700;
            color: #0369a1;
            background: #e0f2fe;
            padding: 4px 12px;
            border-radius: 6px;
            display: inline-block;
        }

        .card-info {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
            height: 100%;
        }

        .card-info h6 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }

        .tabla-items th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 600;
            padding: 10px 12px;
        }

        .tabla-items td {
            padding: 10px 12px;
            vertical-align: middle;
            font-size: 0.88rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .tabla-items tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .cuadro-totales {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 16px;
        }

        .total-destacado {
            background: #0284c7;
            color: #ffffff;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 1.25rem;
            font-weight: 800;
        }

        .firma-box {
            border-top: 1px dashed #94a3b8;
            margin-top: 50px;
            padding-top: 8px;
            text-align: center;
            font-size: 0.82rem;
            color: #475569;
        }

        .watermark-entregado {
            position: absolute;
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            font-size: 5rem;
            font-weight: 900;
            color: rgba(34, 197, 94, 0.12);
            border: 8px dashed rgba(34, 197, 94, 0.2);
            padding: 10px 40px;
            border-radius: 16px;
            text-transform: uppercase;
            pointer-events: none;
            user-select: none;
            z-index: 1;
        }

        /* Estilos de impresión */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }
            .toolbar-remito, .no-print {
                display: none !important;
            }
            .remito-wrapper {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
            .tabla-items th {
                background-color: #0f172a !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .total-destacado {
                background: #0284c7 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .tipo-comprobante-box, .nro-remito-badge, .card-info {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Barra de Herramientas de Acciones (Oculta al imprimir) -->
    <header class="toolbar-remito no-print">
        <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <a href="javascript:window.close();" class="btn btn-outline-light btn-sm" onclick="if(history.length>1){history.back();return false;}">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
                <span class="fw-bold d-none d-md-inline text-white">
                    <i class="bi bi-file-earmark-pdf-fill text-info me-1"></i> Remito Digital: <?= htmlspecialchars($nroRemito, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php if (!$esEntregado): ?>
                    <button type="button" class="btn btn-success btn-sm fw-bold shadow-sm" id="btnMarcarEntregadoToolbar">
                        <i class="bi bi-check2-circle me-1"></i> Marcar Entregado
                    </button>
                <?php else: ?>
                    <span class="badge bg-success px-2 py-1 fs-6 d-flex align-items-center gap-1">
                        <i class="bi bi-check2-all"></i> Entregado
                    </span>
                <?php endif; ?>

                <button type="button" class="btn btn-light btn-sm fw-semibold" onclick="window.print()">
                    <i class="bi bi-printer-fill me-1 text-primary"></i> Imprimir / PDF
                </button>

                <button type="button" class="btn btn-info text-white btn-sm fw-semibold" id="btnDescargarPdfDirecto">
                    <i class="bi bi-download me-1"></i> Descargar PDF
                </button>

                <button type="button" class="btn btn-success btn-sm fw-semibold" id="btnCompartirWhatsapp">
                    <i class="bi bi-whatsapp me-1"></i> WhatsApp
                </button>

                <button type="button" class="btn btn-outline-light btn-sm" id="btnCopiarLink">
                    <i class="bi bi-link-45deg me-1"></i> Copiar Enlace
                </button>
            </div>
        </div>
    </header>

    <!-- Documento Remito Oficial -->
    <main class="container my-3 my-md-4">
        <div class="remito-wrapper" id="remitoDocumento">
            
            <?php if ($esEntregado): ?>
                <div class="watermark-entregado">ENTREGADO</div>
            <?php endif; ?>

            <!-- Encabezado del Remito -->
            <div class="remito-header">
                <div class="row align-items-center gy-3">
                    <!-- Datos Emisor / Empresa -->
                    <div class="col-12 col-md-5">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="bg-primary text-white rounded p-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-snow2 fs-4"></i>
                            </div>
                            <div>
                                <h1 class="h5 fw-bold text-dark mb-0">FÁBRICA DE HIELO</h1>
                                <small class="text-primary fw-bold">DISTRIBUCIÓN Y VENTA MAYORISTA</small>
                            </div>
                        </div>
                        <p class="small text-muted mb-0">
                            <strong>CUIT:</strong> 30-71234567-9<br>
                            <strong>Dirección:</strong> Parque Industrial - Planta Central<br>
                            <strong>Teléfono:</strong> +54 (11) 4567-8900 | <strong>Email:</strong> ventas@hielopolar.com
                        </p>
                    </div>

                    <!-- Tipo Comprobante (R) -->
                    <div class="col-12 col-md-2 text-center">
                        <div class="tipo-comprobante-box">R</div>
                        <span class="small fw-bold text-muted d-block">COD. 091</span>
                        <span class="badge text-bg-light border text-dark" style="font-size: 0.68rem;">NO VÁLIDO COMO FACTURA</span>
                    </div>

                    <!-- Datos del Comprobante -->
                    <div class="col-12 col-md-5 text-md-end">
                        <div class="mb-2">
                            <span class="text-muted small d-block">REMITO DE ENTREGA</span>
                            <span class="nro-remito-badge"><?= htmlspecialchars($nroRemito, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="small text-muted">
                            <div><strong>Fecha de emisión:</strong> <?= date("d/m/Y H:i", strtotime($fechaVenta)) ?> hs</div>
                            <div><strong>Ticket de Venta:</strong> <span class="font-monospace text-dark fw-bold"><?= htmlspecialchars($ticketCod, ENT_QUOTES, 'UTF-8') ?></span></div>
                            <div><strong>Vendedor / Despacho:</strong> <?= htmlspecialchars($vendedorNombre, ENT_QUOTES, 'UTF-8') ?></div>
                            <div><strong>Estado de entrega:</strong> 
                                <span class="badge <?= $esEntregado ? 'text-bg-success' : 'text-bg-warning text-dark' ?>">
                                    <?= $esEntregado ? 'ENTREGADO' : 'PENDIENTE DE ENTREGA' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fila de Información de Cliente y Destino -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-7">
                    <div class="card-info">
                        <h6><i class="bi bi-person-fill text-primary me-1"></i> Datos del Cliente / Destinatario</h6>
                        <div class="row g-2 small">
                            <div class="col-12">
                                <span class="text-muted">Nombre / Razón Social:</span>
                                <strong class="d-block text-dark fs-6"><?= htmlspecialchars($clienteNombre, ENT_QUOTES, 'UTF-8') ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted">Teléfono de contacto:</span>
                                <div class="text-dark fw-medium"><?= $clienteTelefono !== '' ? htmlspecialchars($clienteTelefono, ENT_QUOTES, 'UTF-8') : '—' ?></div>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted">Condición de Venta:</span>
                                <div class="text-dark fw-medium">Contado / Reparto Directo</div>
                            </div>
                            <div class="col-12">
                                <span class="text-muted">Dirección de Entrega:</span>
                                <div class="text-dark fw-semibold"><?= $clienteDireccion !== '' ? htmlspecialchars($clienteDireccion, ENT_QUOTES, 'UTF-8') : 'Entrega en Mostrador / Retira en Planta' ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-5">
                    <div class="card-info">
                        <h6><i class="bi bi-truck text-primary me-1"></i> Logística y Despacho</h6>
                        <div class="small text-muted mb-2">
                            <div><strong>Bolsas de hielo a entregar:</strong> <span class="text-dark fw-bold fs-6"><?= $totalBolsasFisicas ?> unidades</span></div>
                            <div><strong>Fecha de despacho:</strong> <?= date("d/m/Y", strtotime($fechaVenta)) ?></div>
                            <div><strong>Fecha de entrega:</strong> <?= $fechaEntrega ? date("d/m/Y H:i", strtotime($fechaEntrega)) . " hs" : "Pendiente de reparto" ?></div>
                        </div>
                        <div class="alert alert-info py-1 px-2 mb-0 small" role="alert">
                            <i class="bi bi-info-circle me-1"></i> Conservar en cámara o freezer a -18°C.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Ítems / Bolsas de Hielo -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle tabla-items mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 5%;">#</th>
                            <th style="width: 32%;">Descripción / Presentación</th>
                            <th class="text-center" style="width: 14%;">Cantidad</th>
                            <th class="text-end" style="width: 13%;">Precio Lista</th>
                            <th class="text-center" style="width: 10%;">Desc.</th>
                            <th class="text-end" style="width: 13%;">Precio c/Desc.</th>
                            <th class="text-end" style="width: 13%;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itemsVenta as $idx => $it): 
                            $num = $idx + 1;
                            $nombre = (string)($it["producto_nombre"] ?? "Bolsas de Hielo");
                            $tipoVenta = (string)($it["tipo_venta"] ?? "unidad");
                            $cantEmpaque = (int)($it["cantidad_empaque"] ?? $it["cantidad"] ?? 1);
                            $cantFisica = (int)($it["cantidad"] ?? $it["total_unidades"] ?? $cantEmpaque);
                            $precioLista = (float)($it["precio_unitario"] ?? 0);
                            $descPorc = (float)($it["descuento_porcentaje"] ?? 0);
                            $descMonto = (float)($it["descuento_monto"] ?? 0);
                            $precioConDesc = $descPorc > 0 ? ($precioLista * (1 - $descPorc / 100)) : $precioLista;
                            $subtotalItem = (float)($it["total"] ?? 0);
                        ?>
                        <tr>
                            <td class="text-center text-muted fw-bold"><?= $num ?></td>
                            <td>
                                <strong class="text-dark d-block"><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?></strong>
                                <small class="text-muted">
                                    <?= $tipoVenta !== 'unidad' ? ucfirst($tipoVenta) . " ({$cantEmpaque} pack = {$cantFisica} bolsas físicas)" : "Venta individual por bolsa" ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold fs-6 text-dark"><?= $cantFisica ?></span> <small class="text-muted">bolsas</small>
                            </td>
                            <td class="text-end text-muted">
                                $ <?= number_format($precioLista, 2, ',', '.') ?>
                            </td>
                            <td class="text-center">
                                <?php if ($descPorc > 0): ?>
                                    <span class="badge text-bg-danger">-<?= $descPorc ?>%</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold text-primary">
                                $ <?= number_format($precioConDesc, 2, ',', '.') ?>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                $ <?= number_format($subtotalItem, 2, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Resumen Financiero y Totales -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-light rounded border h-100 small text-muted">
                        <h6 class="fw-bold text-dark mb-2">Condiciones de Entrega y Recepción</h6>
                        <ul class="ps-3 mb-0">
                            <li>Mercadería sujeta a control de bultos y temperatura en el acto de descarga.</li>
                            <li>La firma y sello del presente remito acredita la recepción conforme de la totalidad de las bolsas de hielo detalladas.</li>
                            <li>Documento comercial respaldatorio de traslado y entrega.</li>
                        </ul>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="cuadro-totales">
                        <div class="d-flex justify-content-between text-muted mb-2 small">
                            <span>Total de Bolsas de Hielo:</span>
                            <strong class="text-dark fs-6"><?= $totalBolsasFisicas ?> bolsas</strong>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-2 small">
                            <span>Subtotal Precio de Lista:</span>
                            <span>$ <?= number_format($subtotalBruto, 2, ',', '.') ?></span>
                        </div>
                        <?php if ($descuentoTotal > 0): ?>
                        <div class="d-flex justify-content-between text-danger mb-2 small">
                            <span>Bonificación / Descuentos:</span>
                            <strong>-$ <?= number_format($descuentoTotal, 2, ',', '.') ?></strong>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center total-destacado mt-2">
                            <span class="text-uppercase" style="letter-spacing: 0.05em;">TOTAL A PAGAR:</span>
                            <span class="fs-4">$ <?= number_format($totalFinal, 2, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zona de Firmas y Conformidad de Entrega -->
            <div class="row pt-4 mt-2">
                <div class="col-6 col-sm-4">
                    <div class="firma-box">
                        <strong>Entregado por (Chofer / Reparto)</strong><br>
                        <span>Firma y Aclaración</span>
                    </div>
                </div>
                <div class="col-6 col-sm-4">
                    <div class="firma-box">
                        <strong>Recibí Conforme (Cliente)</strong><br>
                        <span>Firma y Aclaración</span>
                    </div>
                </div>
                <div class="col-12 col-sm-4 mt-4 mt-sm-0">
                    <div class="firma-box">
                        <strong>DNI / Sello del Comercio</strong><br>
                        <span>Fecha y Hora de Recepción</span>
                    </div>
                </div>
            </div>

            <div class="text-center text-muted small mt-4 pt-3 border-top" style="font-size: 0.75rem;">
                Remito generado digitalmente por el Sistema Integral de Gestión de Hielo • <?= date("d/m/Y H:i:s") ?>
            </div>

        </div>
    </main>

    <script>
        const csrfToken = document.body.dataset.csrf;
        const ventaId = <?= json_encode($idPrincipal) ?>;
        const ticketId = <?= json_encode($ticketCod) ?>;
        const clienteNombre = <?= json_encode($clienteNombre) ?>;
        const clienteTel = <?= json_encode($clienteTelefono) ?>;
        const totalVenta = <?= json_encode(number_format($totalFinal, 2, ',', '.')) ?>;
        const nroRemito = <?= json_encode($nroRemito) ?>;

        // 1. Descarga directa a PDF con html2pdf
        document.getElementById("btnDescargarPdfDirecto")?.addEventListener("click", () => {
            const elemento = document.getElementById("remitoDocumento");
            const opt = {
                margin:       [8, 8, 8, 8],
                filename:     `Remito_${nroRemito}_${clienteNombre.replace(/[^a-zA-Z0-9]/g, '_')}.pdf`,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            const btn = document.getElementById("btnDescargarPdfDirecto");
            const textoOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Generando PDF...`;

            html2pdf().set(opt).from(elemento).save().then(() => {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;
            }).catch(err => {
                console.error(err);
                alert("Error al generar PDF: " + err.message);
                btn.disabled = false;
                btn.innerHTML = textoOriginal;
            });
        });

        // 2. Compartir por WhatsApp
        document.getElementById("btnCompartirWhatsapp")?.addEventListener("click", () => {
            const urlActual = window.location.href;
            let telLimpio = clienteTel.replace(/[^0-9]/g, '');
            if (telLimpio.startsWith('0')) telLimpio = telLimpio.substring(1);
            if (telLimpio && !telLimpio.startsWith('54')) telLimpio = '549' + telLimpio;

            const mensaje = encodeURIComponent(
                `*REMITO DE ENTREGA - FÁBRICA DE HIELO*\n` +
                `Hola *${clienteNombre}*, adjuntamos el remito de entrega *${nroRemito}* por tu pedido de bolsas de hielo.\n\n` +
                `💰 *Total:* $ ${totalVenta}\n` +
                `📄 *Ver/Descargar comprobante:* ${urlActual}\n\n` +
                `¡Muchas gracias por tu compra!`
            );

            const whatsappUrl = telLimpio 
                ? `https://api.whatsapp.com/send?phone=${telLimpio}&text=${mensaje}`
                : `https://api.whatsapp.com/send?text=${mensaje}`;

            window.open(whatsappUrl, '_blank');
        });

        // 3. Copiar enlace al portapapeles
        document.getElementById("btnCopiarLink")?.addEventListener("click", async () => {
            try {
                await navigator.clipboard.writeText(window.location.href);
                alert("¡Enlace del remito copiado al portapapeles con éxito!");
            } catch {
                prompt("Copia el siguiente enlace:", window.location.href);
            }
        });

        // 4. Marcar como entregado desde la barra de herramientas
        document.getElementById("btnMarcarEntregadoToolbar")?.addEventListener("click", async () => {
            if (!confirm(`¿Marcar este pedido de hielo (${nroRemito}) como ENTREGADO?`)) return;

            const btn = document.getElementById("btnMarcarEntregadoToolbar");
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Guardando...`;

            try {
                const resp = await fetch("marcar_entrega_venta.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-Token": csrfToken
                    },
                    body: JSON.stringify({
                        venta_id: ventaId,
                        ticket_id: ticketId
                    })
                });

                const data = await resp.json();
                if (!resp.ok) throw new Error(data.error || "No se pudo actualizar el estado.");

                alert("¡Pedido marcado como ENTREGADO exitosamente!");
                window.location.reload();
            } catch (err) {
                alert("Error: " + err.message);
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-check2-circle me-1"></i> Marcar Entregado`;
            }
        });
    </script>
</body>
</html>
