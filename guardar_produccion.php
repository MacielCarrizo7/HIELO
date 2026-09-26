<?php
/**
 * Registro Unificado y Rápido de Producción de Bolsas de Hielo (3 kg y 1.5 kg)
 */
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";

requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$inputRaw = file_get_contents("php://input");
$datosJson = json_decode($inputRaw, true);

if (!is_array($datosJson)) {
    $datosJson = [
        "lote" => trim($_POST["lote"] ?? ($_POST["numero_factura"] ?? "")),
        "motivo" => trim($_POST["motivo"] ?? "Producción de fábrica"),
        "sin_comprobante" => !empty($_POST["sin_comprobante"]),
        "produccion" => json_decode($_POST["produccion"] ?? "[]", true)
    ];
}

$lote = trim((string)($datosJson["lote"] ?? ($datosJson["numero_factura"] ?? "")));
$motivo = trim((string)($datosJson["motivo"] ?? "Producción de fábrica"));
$sinComprobante = !empty($datosJson["sin_comprobante"]);
$loteFinal = ($sinComprobante || $lote === "") ? "Sin Comprobante" : $lote;
$produccion = $datosJson["produccion"] ?? [];

if (!is_array($produccion) || empty($produccion)) {
    responderJson(["error" => "Debés ingresar la cantidad de bolsas producidas."], 400);
}

$usuarioId = isset($_SESSION["usuario_id"]) ? (int) $_SESSION["usuario_id"] : null;
$usuarioNombre = trim(($_SESSION["usuario_nombre"] ?? "Admin") . " " . ($_SESSION["usuario_apellido"] ?? ""));
$fechaActual = date("Y-m-d H:i:s");

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $todosLosProductos = $firestore->obtenerColeccion("productos");

    $itemsProcesados = [];
    $totalBolsasSumadas = 0;

    foreach ($produccion as $prodItem) {
        $nombreClave = trim((string)($prodItem["nombre"] ?? ""));
        $cantidadBolsas = (int)($prodItem["cantidad"] ?? ($prodItem["stock"] ?? 0));
        $precioCosto = floatval($prodItem["precio_costo"] ?? 0);
        $precioVenta = floatval($prodItem["precio_venta"] ?? ($prodItem["precio"] ?? 0));
        $productoIdEnviado = (int)($prodItem["id"] ?? 0);

        if ($cantidadBolsas <= 0) {
            // Si la cantidad es 0 no se suma stock para este producto
            continue;
        }

        // Buscar producto existente por ID o por coincidencia de nombre (ej. "3 kg", "3kg", "1.5 kg", "1.5kg")
        $productoExistente = null;

        if ($productoIdEnviado > 0) {
            foreach ($todosLosProductos as $p) {
                if ((int)($p["id"] ?? $p["_id"] ?? 0) === $productoIdEnviado) {
                    $productoExistente = $p;
                    break;
                }
            }
        }

        if (!$productoExistente && $nombreClave !== "") {
            $nomClaveLower = mb_strtolower($nombreClave);
            foreach ($todosLosProductos as $p) {
                $pNomLower = mb_strtolower((string)($p["nombre"] ?? ""));
                if ($pNomLower === $nomClaveLower) {
                    $productoExistente = $p;
                    break;
                }
                // Coincidencia inteligente de peso
                if (str_contains($nomClaveLower, "3 kg") || str_contains($nomClaveLower, "3kg")) {
                    if (str_contains($pNomLower, "3 kg") || str_contains($pNomLower, "3kg") || str_contains($pNomLower, "3 k")) {
                        $productoExistente = $p;
                        break;
                    }
                } elseif (str_contains($nomClaveLower, "1.5 kg") || str_contains($nomClaveLower, "1.5kg") || str_contains($nomClaveLower, "1,5")) {
                    if (str_contains($pNomLower, "1.5 kg") || str_contains($pNomLower, "1.5kg") || str_contains($pNomLower, "1,5")) {
                        $productoExistente = $p;
                        break;
                    }
                }
            }
        }

        $stockAnterior = 0;
        $prodId = 0;
        $nombreProducto = $nombreClave !== "" ? $nombreClave : "Bolsa de Hielo";

        if ($productoExistente) {
            $prodId = (int)($productoExistente["id"] ?? $productoExistente["_id"]);
            $stockAnterior = (int)($productoExistente["stock"] ?? 0);
            $nombreProducto = (string)($productoExistente["nombre"] ?? $nombreProducto);
            $nuevoStock = $stockAnterior + $cantidadBolsas;

            $camposActualizar = ["stock" => $nuevoStock];
            if ($precioVenta > 0) {
                $camposActualizar["precio_venta"] = $precioVenta;
                $camposActualizar["precio"] = $precioVenta;
            }
            if ($precioCosto > 0) {
                $camposActualizar["precio_costo"] = $precioCosto;
            }

            $firestore->actualizarCampos("productos", (string)$prodId, $camposActualizar);
        } else {
            // Si el producto no existe aún en Firestore, crearlo automáticamente con su código de barras
            $prodId = FirestoreConexion::obtenerSiguienteIdProducto();
            $stockAnterior = 0;
            $nuevoStock = $cantidadBolsas;

            // Generar código de barras EAN-13
            $primeros12 = "20";
            for ($i = 0; $i < 10; $i++) {
                $primeros12 .= (string)random_int(0, 9);
            }
            $suma = 0;
            for ($i = 0; $i < 12; $i++) {
                $d = (int)$primeros12[$i];
                $suma += ($i % 2 === 0) ? $d : $d * 3;
            }
            $digitoControl = (10 - ($suma % 10)) % 10;
            $codigoBarras = $primeros12 . $digitoControl;

            $nuevoProdDoc = [
                "id" => $prodId,
                "codigo" => "HIE-" . $prodId,
                "codigo_barras" => $codigoBarras,
                "nombre" => $nombreProducto,
                "descripcion" => "Bolsa de Hielo de fabricación propia envasada en planta.",
                "presentacion" => "unidad",
                "permite_venta_unidad" => true,
                "precio" => $precioVenta > 0 ? $precioVenta : 1000.0,
                "precio_venta" => $precioVenta > 0 ? $precioVenta : 1000.0,
                "precio_costo" => $precioCosto > 0 ? $precioCosto : 400.0,
                "stock" => $nuevoStock,
                "categoria_id" => null,
                "categoria" => "Hielo en Cubos",
                "categoria_nombre" => "Hielo en Cubos",
                "unidades_por_bulto" => 1,
                "creado_el" => $fechaActual
            ];

            $firestore->guardarDocumento("productos", (string)$prodId, $nuevoProdDoc);
        }

        $totalBolsasSumadas += $cantidadBolsas;

        // Registrar en ingresos_stock (Kardex de Producción)
        try {
            $ingresoId = $firestore->obtenerSiguienteId("contadores", "ingresos", "ultimo_id");
            $ingresoDoc = [
                "id" => $ingresoId,
                "producto_id" => $prodId,
                "producto_nombre" => $nombreProducto,
                "cantidad" => $cantidadBolsas,
                "presentacion" => "unidad",
                "unidades_por_bulto" => 1,
                "total_unidades" => $cantidadBolsas,
                "precio_unitario" => $precioVenta,
                "precio_costo" => $precioCosto,
                "precio_venta" => $precioVenta,
                "numero_factura" => $loteFinal,
                "sin_factura" => $sinComprobante,
                "usuario_id" => $usuarioId,
                "usuario" => $usuarioNombre,
                "motivo" => "Producción de Fábrica (" . ($loteFinal ? "Lote: {$loteFinal}" : "Sin lote") . ") - {$motivo}",
                "fecha" => $fechaActual
            ];
            $firestore->guardarDocumento("ingresos_stock", (string)$ingresoId, $ingresoDoc);
        } catch (Throwable $eIng) {
            error_log("Aviso al guardar en ingresos_stock: " . $eIng->getMessage());
        }

        // Registrar Trazabilidad en Kardex / Movimientos
        try {
            FirestoreConexion::registrarMovimientoProducto(
                productoId: $prodId,
                tipo: "PRODUCCION",
                descripcion: "Producción de Fábrica: +{$cantidadBolsas} bolsas ingresadas a cámara (Lote: {$loteFinal}). Stock previo: {$stockAnterior} un. -> Nuevo: {$nuevoStock} un.",
                cantidadAnterior: $stockAnterior,
                cantidadNueva: $nuevoStock,
                diferencia: $cantidadBolsas,
                precioAnterior: $precioVenta,
                precioNuevo: $precioVenta,
                usuarioId: $usuarioId,
                usuarioNombre: $usuarioNombre
            );
        } catch (Throwable $eMov) {
            error_log("Aviso al registrar movimiento: " . $eMov->getMessage());
        }

        $itemsProcesados[] = [
            "producto_id" => $prodId,
            "nombre" => $nombreProducto,
            "bolsas_sumadas" => $cantidadBolsas,
            "stock_anterior" => $stockAnterior,
            "stock_nuevo" => $nuevoStock
        ];
    }

    if (empty($itemsProcesados)) {
        responderJson(["error" => "No se especificó ninguna cantidad de bolsas para ingresar."], 400);
    }

    responderJson([
        "success" => true,
        "total_bolsas" => $totalBolsasSumadas,
        "items" => $itemsProcesados,
        "lote" => $loteFinal,
        "mensaje" => "¡Producción registrada exitosamente! Se sumaron {$totalBolsasSumadas} bolsas a la cámara de frío."
    ], 200);

} catch (Throwable $e) {
    error_log("Error en guardar_produccion.php: " . $e->getMessage());
    responderJson(["error" => "No se pudo registrar la producción: " . $e->getMessage()], 500);
}
?>
