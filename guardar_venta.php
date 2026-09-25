<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
requerirCsrfJson();

$inputRaw = file_get_contents("php://input");
$datosJson = json_decode($inputRaw, true);

if (!is_array($datosJson)) {
    // Si viene por POST tradicional o form-data
    $clienteNombre = trim($_POST["cliente_nombre"] ?? ($_POST["cliente_razon_social"] ?? ""));
    $clienteTelefono = trim($_POST["cliente_telefono"] ?? "");
    $clienteDireccion = trim($_POST["cliente_direccion"] ?? "");
    $clienteId = filter_input(INPUT_POST, "cliente_id", FILTER_VALIDATE_INT) ?: 0;

    $itemsRaw = $_POST["items"] ?? null;
    if ($itemsRaw && is_string($itemsRaw)) {
        $items = json_decode($itemsRaw, true) ?: [];
    } elseif (isset($_POST["producto_id"])) {
        $items = [[
            "producto_id" => filter_input(INPUT_POST, "producto_id", FILTER_VALIDATE_INT) ?: 0,
            "cantidad" => filter_input(INPUT_POST, "cantidad", FILTER_VALIDATE_INT) ?: 0,
            "tipo_venta" => trim($_POST["tipo_venta"] ?? "unidad"),
            "descuento_porcentaje" => floatval($_POST["descuento_porcentaje"] ?? 0)
        ]];
    } else {
        $items = [];
    }
} else {
    // JSON Payload
    $clienteNombre = trim((string)($datosJson["cliente_nombre"] ?? ($datosJson["cliente_razon_social"] ?? "")));
    $clienteTelefono = trim((string)($datosJson["cliente_telefono"] ?? ""));
    $clienteDireccion = trim((string)($datosJson["cliente_direccion"] ?? ""));
    $clienteId = (int) ($datosJson["cliente_id"] ?? 0);
    $items = $datosJson["items"] ?? [];

    if (empty($items) && isset($datosJson["producto_id"])) {
        $items = [[
            "producto_id" => (int) ($datosJson["producto_id"] ?? 0),
            "cantidad" => (int) ($datosJson["cantidad"] ?? 0),
            "tipo_venta" => trim($datosJson["tipo_venta"] ?? "unidad"),
            "descuento_porcentaje" => floatval($datosJson["descuento_porcentaje"] ?? 0)
        ]];
    }
}

// Validación de Cliente Rápido
if ($clienteNombre === "") {
    $clienteNombre = "Consumidor Final";
}

if (!is_array($items) || empty($items)) {
    responderJson(["error" => "El carrito de ventas está vacío. Agregá al menos un producto."], 400);
}

// Límite de descuento para vendedores
$rol = $_SESSION["usuario_rol"] ?? "";
$usuarioId = (int) ($_SESSION["usuario_id"] ?? 0);
$tiposValidos = ["unidad", "caja", "bulto"];

try {
    $firestore = FirestoreConexion::obtenerFirestore();

    // Obtener límite de descuento en vivo desde Firestore
    if ($rol === "admin") {
        $limiteVendedor = 100.0;
    } else {
        $usuarioDoc = $usuarioId > 0 ? $firestore->obtenerDocumento("usuarios", (string)$usuarioId) : null;
        if ($usuarioDoc && isset($usuarioDoc["limite_descuento"])) {
            $limiteVendedor = (float)$usuarioDoc["limite_descuento"];
            $_SESSION["usuario_limite_descuento"] = $limiteVendedor;
        } else {
            $limiteVendedor = isset($_SESSION["usuario_limite_descuento"]) ? (float)$_SESSION["usuario_limite_descuento"] : 100.0;
        }
    }

    // 1. Pre-validar stock y datos de cada producto en el carrito
    $itemsValidados = [];

    foreach ($items as $idx => $item) {
        $numItem = $idx + 1;
        $prodId = (int) ($item["producto_id"] ?? ($item["id"] ?? 0));
        
        // Determinar cantidad solicitada
        if (isset($item["cantidad_empaque"]) && (int)$item["cantidad_empaque"] > 0) {
            $cant = (int)$item["cantidad_empaque"];
        } elseif (isset($item["cantidad"]) && (int)$item["cantidad"] > 0) {
            $cant = (int)$item["cantidad"];
        } elseif (isset($item["total_unidades"]) && (int)$item["total_unidades"] > 0) {
            $cant = (int)$item["total_unidades"];
        } else {
            $cant = 0;
        }

        $tipoVenta = trim($item["tipo_venta"] ?? "unidad");
        $descPorc = floatval($item["descuento_porcentaje"] ?? ($item["descuento"] ?? 0));

        if ($prodId <= 0 || $cant <= 0) {
            throw new DomainException("Ítem #{$numItem}: Producto o cantidad inválida.");
        }
        if (!in_array($tipoVenta, $tiposValidos, true)) {
            $tipoVenta = "unidad";
        }
        if ($descPorc < 0 || $descPorc > 100) {
            throw new DomainException("Ítem #{$numItem}: El porcentaje de descuento debe estar entre 0% y 100%.");
        }
        if ($rol === "vendedor" && $descPorc > ($limiteVendedor + 0.001)) {
            throw new DomainException("Ítem #{$numItem}: El descuento aplicado ({$descPorc}%) supera tu límite autorizado ({$limiteVendedor}%).");
        }

        $producto = $firestore->obtenerDocumento("productos", (string)$prodId);
        if (!$producto) {
            throw new DomainException("Ítem #{$numItem}: Producto no encontrado en la base de datos.");
        }

        $nombreProd = (string) ($producto["nombre"] ?? "Producto #{$prodId}");
        $presProducto = (string) ($producto["presentacion"] ?? "unidad");
        $permiteVentaUnidad = isset($producto["permite_venta_unidad"]) ? (bool)$producto["permite_venta_unidad"] : ($presProducto === "unidad");

        // Validar que no se intente vender por unidad si está deshabilitado
        if ($tipoVenta === "unidad" && $presProducto !== "unidad" && !$permiteVentaUnidad) {
            throw new DomainException("Ítem #{$numItem} ('{$nombreProd}'): No se permite la venta por unidad suelta. Debe venderse en su presentación empaquetada ({$presProducto}).");
        }

        // Validar que no se intente vender en una presentación empaquetada que no corresponde
        if ($tipoVenta !== "unidad" && $tipoVenta !== $presProducto) {
            throw new DomainException("Ítem #{$numItem} ('{$nombreProd}'): Presentación '{$tipoVenta}' no permitida. Este producto está configurado como '{$presProducto}'.");
        }

        $unidadesPorEmpaque = max(1, (int) ($producto["unidades_por_bulto"] ?? 1));
        $totalUnidades = ($tipoVenta === "caja" || $tipoVenta === "bulto") ? ($cant * $unidadesPorEmpaque) : $cant;
        $stockActual = (int) ($producto["stock"] ?? 0);

        if ($stockActual < $totalUnidades) {
            throw new DomainException("Stock insuficiente para '{$nombreProd}'. Disponible: {$stockActual} un. (solicitadas: {$totalUnidades} un.).");
        }

        $precioUnitario = (float) $producto["precio"];
        $subtotal = $precioUnitario * $totalUnidades;
        $descuentoMonto = round($subtotal * ($descPorc / 100), 2);
        $totalItem = max(0, $subtotal - $descuentoMonto);

        $itemsValidados[] = [
            "producto_id" => $prodId,
            "producto_nombre" => (string)$producto["nombre"],
            "tipo_venta" => $tipoVenta,
            "cantidad_empaque" => $cant,
            "unidades_por_bulto" => $unidadesPorEmpaque,
            "total_unidades" => $totalUnidades,
            "stock_actual" => $stockActual,
            "precio_unitario" => $precioUnitario,
            "subtotal" => $subtotal,
            "descuento_porcentaje" => $descPorc,
            "descuento_monto" => $descuentoMonto,
            "total" => $totalItem
        ];
    }

    $usuarioId = (int) $_SESSION["usuario_id"];
    $usuarioNombre = trim(($_SESSION["usuario_nombre"] ?? "Usuario") . " " . ($_SESSION["usuario_apellido"] ?? ""));
    $fechaActual = date("Y-m-d H:i:s");
    $ticketId = "TK-" . date("Ymd-His") . "-" . str_pad((string)random_int(100, 999), 3, "0", STR_PAD_LEFT);

    // 2. Unificación en UNA SOLA VENTA MULTIPRODUCTO
    $ventaId = FirestoreConexion::obtenerSiguienteIdVenta();
    
    $nombresProductos = [];
    $itemsDetalleResumen = [];
    $totalUnidadesGeneral = 0;
    $totalSubtotalGeneral = 0;
    $totalDescuentoGeneral = 0;
    $totalVentaGeneral = 0;

    foreach ($itemsValidados as $iv) {
        $prodId = $iv["producto_id"];
        $totalUnidades = $iv["total_unidades"];
        $nuevoStock = $iv["stock_actual"] - $totalUnidades;

        $totalUnidadesGeneral += $totalUnidades;
        $totalSubtotalGeneral += $iv["subtotal"];
        $totalDescuentoGeneral += $iv["descuento_monto"];
        $totalVentaGeneral += $iv["total"];

        $nombresProductos[] = "{$iv['producto_nombre']} ({$totalUnidades} un.)";

        $itemsDetalleResumen[] = [
            "producto_id" => $prodId,
            "producto_nombre" => $iv["producto_nombre"],
            "tipo_venta" => $iv["tipo_venta"],
            "cantidad_empaque" => $iv["cantidad_empaque"],
            "cantidad" => $totalUnidades,
            "precio_unitario" => $iv["precio_unitario"],
            "subtotal" => $iv["subtotal"],
            "descuento_porcentaje" => $iv["descuento_porcentaje"],
            "descuento_monto" => $iv["descuento_monto"],
            "total" => $iv["total"]
        ];

        // Descontar Stock en Firestore para cada producto
        $firestore->actualizarCampos("productos", (string)$prodId, ["stock" => $nuevoStock]);

        // Registrar trazabilidad de movimientos
        $infoContactoCliente = ($clienteTelefono !== "" || $clienteDireccion !== "") ? " [Tel: {$clienteTelefono} | Dir: {$clienteDireccion}]" : "";
        FirestoreConexion::registrarMovimientoProducto(
            productoId: $prodId,
            tipo: "VENTA",
            descripcion: "Venta #{$ventaId} [{$ticketId}] a {$clienteNombre}{$infoContactoCliente} ({$totalUnidades} un. por $" . number_format($iv["total"], 2) . ")",
            cantidadAnterior: $iv["stock_actual"],
            cantidadNueva: $nuevoStock,
            diferencia: -$totalUnidades,
            precioAnterior: $iv["precio_unitario"],
            precioNuevo: $iv["precio_unitario"],
            usuarioId: $usuarioId,
            usuarioNombre: $usuarioNombre
        );
    }

    $esVentaUnica = (count($itemsValidados) === 1);
    $productoNombreResumen = $esVentaUnica ? $itemsValidados[0]["producto_nombre"] : implode(" • ", $nombresProductos);
    $tipoVentaResumen = $esVentaUnica ? $itemsValidados[0]["tipo_venta"] : "combo";
    $precioUnitarioResumen = $esVentaUnica ? $itemsValidados[0]["precio_unitario"] : ($totalUnidadesGeneral > 0 ? round($totalVentaGeneral / $totalUnidadesGeneral, 2) : 0);
    $descuentoPorcResumen = ($totalSubtotalGeneral > 0 && $totalDescuentoGeneral > 0) ? round(($totalDescuentoGeneral / $totalSubtotalGeneral) * 100, 2) : ($esVentaUnica ? $itemsValidados[0]["descuento_porcentaje"] : 0);

    // Guardar Documento Único de Venta en Firestore
    $ventaDoc = [
        "id" => $ventaId,
        "ticket_id" => $ticketId,
        "producto_id" => $esVentaUnica ? $itemsValidados[0]["producto_id"] : null,
        "producto_nombre" => $productoNombreResumen,
        "tipo_venta" => $tipoVentaResumen,
        "cantidad_empaque" => $esVentaUnica ? $itemsValidados[0]["cantidad_empaque"] : $totalUnidadesGeneral,
        "cantidad" => $totalUnidadesGeneral,
        "precio_unitario" => $precioUnitarioResumen,
        "subtotal" => $totalSubtotalGeneral,
        "descuento_porcentaje" => $descuentoPorcResumen,
        "descuento_monto" => $totalDescuentoGeneral,
        "total" => $totalVentaGeneral,
        "items" => $itemsDetalleResumen,
        "usuario_id" => $usuarioId,
        "vendedor_id" => $usuarioId,
        "vendedor" => $usuarioNombre,
        "vendedor_nombre" => $usuarioNombre,
        "cliente_id" => $clienteId,
        "cliente_nombre" => $clienteNombre,
        "cliente_telefono" => $clienteTelefono,
        "cliente_direccion" => $clienteDireccion,
        "estado" => "ACTIVA",
        "fecha" => $fechaActual
    ];
    $firestore->guardarDocumento("ventas", (string)$ventaId, $ventaDoc);

    // Guardar Detalle en detalle_ventas
    $detalleDoc = [
        "id" => $ventaId,
        "venta_id" => $ventaId,
        "ticket_id" => $ticketId,
        "items" => $itemsDetalleResumen,
        "total_unidades" => $totalUnidadesGeneral,
        "subtotal" => $totalSubtotalGeneral,
        "descuento_monto" => $totalDescuentoGeneral,
        "total" => $totalVentaGeneral,
        "usuario_id" => $usuarioId,
        "vendedor" => $usuarioNombre,
        "vendedor_nombre" => $usuarioNombre,
        "cliente_nombre" => $clienteNombre,
        "cliente_telefono" => $clienteTelefono,
        "cliente_direccion" => $clienteDireccion,
        "fecha" => $fechaActual
    ];
    $firestore->guardarDocumento("detalle_ventas", (string)$ventaId, $detalleDoc);

    // Historial de la transacción
    $histId = FirestoreConexion::obtenerSiguienteIdHistorial();
    $historialDoc = [
        "id" => $histId,
        "venta_id" => $ventaId,
        "ticket_id" => $ticketId,
        "usuario_id" => $usuarioId,
        "tipo" => "VENTA_CREADA",
        "cantidad_nueva" => $totalUnidadesGeneral,
        "total_nuevo" => $totalVentaGeneral,
        "items_count" => count($itemsValidados),
        "estado_anterior" => "NUEVA",
        "estado_nuevo" => "ACTIVA",
        "fecha" => $fechaActual
    ];
    $firestore->guardarDocumento("venta_historial", (string)$histId, $historialDoc);

    responderJson([
        "success" => true,
        "id" => $ventaId,
        "ticket_id" => $ticketId,
        "cliente" => $clienteNombre,
        "total_items" => count($itemsValidados),
        "total_unidades" => $totalUnidadesGeneral,
        "descuento_total" => $totalDescuentoGeneral,
        "total" => $totalVentaGeneral,
        "ventas_ids" => [$ventaId],
        "mensaje" => "Venta #" . $ventaId . " [" . $ticketId . "] de " . count($itemsValidados) . " producto(s) a '{$clienteNombre}' registrada con éxito."
    ], 201);

} catch (DomainException $e) {
    responderJson(["error" => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log("Error al guardar venta unificada en Firestore: " . $e->getMessage());
    responderJson(["error" => "No se pudo registrar la venta: " . $e->getMessage()], 500);
}
?>
