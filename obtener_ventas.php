<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
header("Content-Type: application/json; charset=UTF-8");

$rolSesion = (string)($_SESSION["usuario_rol"] ?? "");
$usuarioIdSesion = (int)($_SESSION["usuario_id"] ?? 0);
$usuarioNombreSesion = trim(($_SESSION["usuario_nombre"] ?? "") . " " . ($_SESSION["usuario_apellido"] ?? ""));

$desde = trim($_GET["desde"] ?? "");
$hasta = trim($_GET["hasta"] ?? "");
$productoIdTexto = trim($_GET["producto_id"] ?? "");
$clienteIdTexto = trim($_GET["cliente_id"] ?? "");
$clienteNombreFiltro = mb_strtolower(trim($_GET["cliente_nombre"] ?? ""));
$estado = strtoupper(trim($_GET["estado"] ?? ""));
$vendedorIdTexto = trim($_GET["vendedor_id"] ?? "");
$estadosValidos = ["ACTIVA", "ENTREGADO", "MODIFICADA", "CANCELADA"];

if (($desde !== "" && !fechaIsoValida($desde)) || ($hasta !== "" && !fechaIsoValida($hasta))) {
    responderJson(["error" => "Ingresá fechas válidas."], 400);
}
if ($desde !== "" && $hasta !== "" && $desde > $hasta) {
    responderJson(["error" => "La fecha desde no puede ser posterior a la fecha hasta."], 400);
}
if ($estado !== "" && !in_array($estado, $estadosValidos, true)) {
    responderJson(["error" => "El estado seleccionado no es válido."], 400);
}

$prodIdFiltro = ($productoIdTexto !== "" && ctype_digit($productoIdTexto)) ? (int)$productoIdTexto : null;
$cliIdFiltro = ($clienteIdTexto !== "" && ctype_digit($clienteIdTexto)) ? (int)$clienteIdTexto : null;
$vendIdFiltro = ($vendedorIdTexto !== "" && ctype_digit($vendedorIdTexto)) ? (int)$vendedorIdTexto : null;

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $todasLasVentas = $firestore->obtenerColeccion("ventas");
    $todosLosUsuarios = $firestore->obtenerColeccion("usuarios");

    $mapaUsuarios = [];
    foreach ($todosLosUsuarios as $u) {
        $uId = (int) ($u["id"] ?? $u["_id"] ?? 0);
        if ($uId > 0) {
            $mapaUsuarios[$uId] = trim(($u["nombre"] ?? "") . " " . ($u["apellido"] ?? ""));
        }
    }

    $ventasFiltradas = [];

    foreach ($todasLasVentas as $v) {
        $id = (int) ($v["id"] ?? $v["_id"] ?? 0);
        $prodId = (int) ($v["producto_id"] ?? 0);
        $cliId = (int) ($v["cliente_id"] ?? 0);
        $cliNombreDoc = (string) ($v["cliente_nombre"] ?? ($mapaUsuarios[$cliId] ?? ""));
        $usuId = (int) ($v["usuario_id"] ?? ($v["vendedor_id"] ?? 0));
        $vendedorNombreDoc = (string) ($v["vendedor_nombre"] ?? ($v["vendedor"] ?? ($mapaUsuarios[$usuId] ?? "")));
        $est = (string) ($v["estado"] ?? "ACTIVA");
        $fecha = (string) ($v["fecha"] ?? "");
        $ticketId = (string) ($v["ticket_id"] ?? "");

        // 1. CONTROL DE ACCESO POR ROL:
        // Si el usuario logueado es "vendedor", únicamente puede ver sus propias ventas
        if ($rolSesion === "vendedor") {
            $esPropia = false;
            if ($usuarioIdSesion > 0 && $usuId === $usuarioIdSesion) {
                $esPropia = true;
            } elseif ($usuarioNombreSesion !== "" && mb_strtolower(trim($vendedorNombreDoc)) === mb_strtolower($usuarioNombreSesion)) {
                $esPropia = true;
            }
            if (!$esPropia) {
                continue;
            }
        }

        // Filtro por fecha desde
        if ($desde !== "" && $fecha !== "" && substr($fecha, 0, 10) < $desde) {
            continue;
        }

        // Filtro por fecha hasta
        if ($hasta !== "" && $fecha !== "" && substr($fecha, 0, 10) > $hasta) {
            continue;
        }

        // Filtro por producto
        if ($prodIdFiltro !== null && $prodId !== $prodIdFiltro) {
            continue;
        }

        // Filtro por cliente ID
        if ($cliIdFiltro !== null && $cliId !== $cliIdFiltro) {
            continue;
        }

        // Filtro por cliente texto/nombre
        if ($clienteNombreFiltro !== "" && !str_contains(mb_strtolower($cliNombreDoc), $clienteNombreFiltro)) {
            continue;
        }

        // Filtro por vendedor (aplicable al rol admin)
        if ($rolSesion === "admin" && $vendIdFiltro !== null && $usuId !== $vendIdFiltro) {
            continue;
        }

        // Filtro por estado
        if ($estado !== "" && $est !== $estado) {
            continue;
        }

        $precioUnit = (float) ($v["precio_unitario"] ?? 0);
        $descPorc = (float) ($v["descuento_porcentaje"] ?? 0);
        $precioUnitConDesc = $descPorc > 0 ? ($precioUnit * (1 - $descPorc / 100)) : $precioUnit;

        $ventasFiltradas[] = [
            "id" => $id,
            "ticket_id" => $ticketId,
            "producto_id" => $prodId,
            "producto_nombre" => (string) ($v["producto_nombre"] ?? ""),
            "tipo_venta" => (string) ($v["tipo_venta"] ?? "unidad"),
            "cantidad_empaque" => (int) ($v["cantidad_empaque"] ?? $v["cantidad"] ?? 0),
            "cantidad" => (int) ($v["cantidad"] ?? 0),
            "precio_unitario" => $precioUnit,
            "precio_unitario_con_descuento" => $precioUnitConDesc,
            "subtotal" => (float) ($v["subtotal"] ?? 0),
            "descuento_porcentaje" => $descPorc,
            "descuento_monto" => (float) ($v["descuento_monto"] ?? 0),
            "total" => (float) ($v["total"] ?? 0),
            "fecha" => $fecha,
            "estado" => $est,
            "fecha_entrega" => !empty($v["fecha_entrega"]) ? (string)$v["fecha_entrega"] : null,
            "fecha_modificacion" => !empty($v["fecha_modificacion"]) ? (string)$v["fecha_modificacion"] : null,
            "motivo_cancelacion" => !empty($v["motivo_cancelacion"]) ? (string)$v["motivo_cancelacion"] : null,
            "cliente_id" => $cliId,
            "usuario_id" => $usuId,
            "cliente" => !empty($v["cliente_nombre"]) ? (string)$v["cliente_nombre"] : ($mapaUsuarios[$cliId] ?? "Consumidor Final"),
            "cliente_telefono" => (string)($v["cliente_telefono"] ?? ""),
            "cliente_direccion" => (string)($v["cliente_direccion"] ?? ""),
            "vendedor" => $vendedorNombreDoc !== "" ? $vendedorNombreDoc : ($mapaUsuarios[$usuId] ?? "—")
        ];
    }

    usort($ventasFiltradas, function ($a, $b) {
        $cmp = strcmp($b["fecha"], $a["fecha"]);
        if ($cmp !== 0) return $cmp;
        return $b["id"] <=> $a["id"];
    });

    echo json_encode($ventasFiltradas, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_ventas (Firestore): " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
