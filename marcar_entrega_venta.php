<?php
/**
 * Endpoint para marcar ventas/pedidos de hielo como "ENTREGADO" en Firestore
 */
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
requerirCsrfJson();

$inputRaw = file_get_contents("php://input");
$datosJson = json_decode($inputRaw, true);

if (is_array($datosJson)) {
    $ventaId = (int) ($datosJson["venta_id"] ?? 0);
    $ticketId = trim((string)($datosJson["ticket_id"] ?? ""));
} else {
    $ventaId = filter_input(INPUT_POST, "venta_id", FILTER_VALIDATE_INT) ?: 0;
    $ticketId = trim((string)($_POST["ticket_id"] ?? ""));
}

if ($ventaId <= 0 && $ticketId === "") {
    responderJson(["error" => "Identificador de venta o ticket no especificado."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $usuarioId = (int) $_SESSION["usuario_id"];
    $rol = $_SESSION["usuario_rol"] ?? "vendedor";
    $fechaActual = date("Y-m-d H:i:s");
    $ventasActualizadas = [];

    if ($ticketId !== "") {
        // Buscar todas las ventas asociadas al ticket
        $todasLasVentas = $firestore->obtenerColeccion("ventas");
        foreach ($todasLasVentas as $v) {
            if (($v["ticket_id"] ?? "") === $ticketId) {
                $id = (int)($v["id"] ?? $v["_id"] ?? 0);
                if ($id > 0) {
                    $ventasActualizadas[] = $id;
                    $estadoAnt = (string)($v["estado"] ?? "ACTIVA");
                    if ($estadoAnt !== "CANCELADA") {
                        $firestore->actualizarCampos("ventas", (string)$id, [
                            "estado" => "ENTREGADO",
                            "fecha_entrega" => $fechaActual,
                            "fecha_modificacion" => $fechaActual,
                            "entregado_por_id" => $usuarioId
                        ]);

                        // Historial
                        $histId = FirestoreConexion::obtenerSiguienteIdHistorial();
                        $firestore->guardarDocumento("venta_historial", (string)$histId, [
                            "id" => $histId,
                            "venta_id" => $id,
                            "ticket_id" => $ticketId,
                            "usuario_id" => $usuarioId,
                            "tipo" => "VENTA_ENTREGADA",
                            "estado_anterior" => $estadoAnt,
                            "estado_nuevo" => "ENTREGADO",
                            "fecha" => $fechaActual
                        ]);
                    }
                }
            }
        }
    }

    // Si no se encontró por ticket o se pasó ventaId directo
    if (empty($ventasActualizadas) && $ventaId > 0) {
        $venta = $firestore->obtenerDocumento("ventas", (string)$ventaId);
        if (!$venta) {
            responderJson(["error" => "Venta no encontrada en la base de datos."], 404);
        }

        $estadoAnt = (string)($venta["estado"] ?? "ACTIVA");
        if ($estadoAnt === "CANCELADA") {
            responderJson(["error" => "No se puede marcar como entregada una venta cancelada."], 400);
        }

        $firestore->actualizarCampos("ventas", (string)$ventaId, [
            "estado" => "ENTREGADO",
            "fecha_entrega" => $fechaActual,
            "fecha_modificacion" => $fechaActual,
            "entregado_por_id" => $usuarioId
        ]);

        $histId = FirestoreConexion::obtenerSiguienteIdHistorial();
        $firestore->guardarDocumento("venta_historial", (string)$histId, [
            "id" => $histId,
            "venta_id" => $ventaId,
            "ticket_id" => (string)($venta["ticket_id"] ?? ""),
            "usuario_id" => $usuarioId,
            "tipo" => "VENTA_ENTREGADA",
            "estado_anterior" => $estadoAnt,
            "estado_nuevo" => "ENTREGADO",
            "fecha" => $fechaActual
        ]);

        $ventasActualizadas[] = $ventaId;
    }

    if (empty($ventasActualizadas)) {
        responderJson(["error" => "No se encontraron ventas para actualizar."], 404);
    }

    responderJson([
        "success" => true,
        "estado" => "ENTREGADO",
        "fecha_entrega" => $fechaActual,
        "ventas_afectadas" => $ventasActualizadas,
        "mensaje" => "Pedido marcado como ENTREGADO exitosamente."
    ]);
} catch (Throwable $e) {
    error_log("Error en marcar_entrega_venta: " . $e->getMessage());
    responderJson(["error" => "Error al actualizar estado en Firestore: " . $e->getMessage()], 500);
}
