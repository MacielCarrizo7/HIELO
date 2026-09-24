<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin"]);
require_once __DIR__ . "/FirestoreConexion.php";

$firestore = FirestoreConexion::obtenerFirestore();
$error = "";
$rolesValidos = ["admin", "vendedor"];

if (!isset($_SESSION["csrf_crear_usuario"])) {
    $_SESSION["csrf_crear_usuario"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"]) && $_POST["accion"] === "actualizar_limite") {
    $token = $_POST["csrf_token"] ?? "";
    $userId = filter_input(INPUT_POST, "usuario_id", FILTER_VALIDATE_INT);
    $limiteDesc = filter_input(INPUT_POST, "limite_descuento", FILTER_VALIDATE_FLOAT);

    if (hash_equals($_SESSION["csrf_crear_usuario"], $token) && $userId > 0 && $limiteDesc !== false && $limiteDesc >= 0 && $limiteDesc <= 100) {
        $firestore->actualizarCampos("usuarios", (string)$userId, ["limite_descuento" => (float)$limiteDesc]);
        header("Location: registro.php?limite_actualizado=1");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && (!isset($_POST["accion"]) || $_POST["accion"] === "crear_usuario")) {
    $token = $_POST["csrf_token"] ?? "";
    $dni = trim($_POST["dni"] ?? "");
    $nombre = trim($_POST["nombre"] ?? "");
    $apellido = trim($_POST["apellido"] ?? "");
    $password = $_POST["password"] ?? "";
    $rol = $_POST["rol"] ?? "";
    $limiteDescuento = filter_input(INPUT_POST, "limite_descuento", FILTER_VALIDATE_FLOAT);
    if ($limiteDescuento === false || $limiteDescuento === null || $limiteDescuento < 0 || $limiteDescuento > 100) {
        $limiteDescuento = ($rol === "vendedor") ? 15.0 : 100.0;
    }

    if (!hash_equals($_SESSION["csrf_crear_usuario"], $token)) {
        http_response_code(400);
        $error = "La sesión del formulario venció. Actualizá la página e intentá nuevamente.";
    } elseif ($dni === "" || $nombre === "" || $apellido === "" || $password === "") {
        $error = "Completá todos los campos obligatorios.";
    } elseif (mb_strlen($dni) > 20 || mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) {
        $error = "Uno de los datos supera la longitud permitida.";
    } elseif (!in_array($rol, $rolesValidos, true)) {
        $error = "Seleccioná un rol válido.";
    } else {
        try {
            $check = $firestore->consultar("usuarios", [["dni", "==", $dni]]);

            if (!empty($check)) {
                $error = "Ya existe una cuenta registrada con el DNI ingresado.";
            } else {
                $nuevoId = FirestoreConexion::obtenerSiguienteIdUsuario();
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $nuevoUsuario = [
                    "id" => $nuevoId,
                    "dni" => $dni,
                    "nombre" => $nombre,
                    "apellido" => $apellido,
                    "password" => $hash,
                    "rol" => $rol,
                    "activo" => 1,
                    "limite_descuento" => (float)$limiteDescuento,
                    "fecha_registro" => date("Y-m-d H:i:s"),
                    "totp_secret_encrypted" => null,
                    "totp_enabled" => 0,
                    "totp_confirmed_at" => null,
                    "totp_last_timeslice" => null,
                ];

                $firestore->guardarDocumento("usuarios", (string)$nuevoId, $nuevoUsuario, true);

                $_SESSION["csrf_crear_usuario"] = bin2hex(random_bytes(32));
                header("Location: registro.php?creado=1");
                exit;
            }
        } catch (Throwable $e) {
            error_log("Error al crear usuario en Firestore: " . $e->getMessage());
            $error = "Ocurrió un error al registrar el usuario en la base de datos.";
        }
    }
}

try {
    $usuarios = $firestore->consultar("usuarios");
    usort($usuarios, function ($a, $b) {
        $fechaA = $a["fecha_registro"] ?? "";
        $fechaB = $b["fecha_registro"] ?? "";
        if ($fechaA === $fechaB) {
            return ($b["id"] ?? 0) <=> ($a["id"] ?? 0);
        }
        return strcmp($fechaB, $fechaA);
    });
} catch (Throwable $e) {
    error_log("Error al listar usuarios desde Firestore: " . $e->getMessage());
    $usuarios = [];
}

$equipo = array_filter($usuarios, fn($u) => ($u["rol"] ?? "") === "admin" || ($u["rol"] ?? "") === "vendedor");

function e(string $valor): string {
    return htmlspecialchars($valor, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content">
    <title>Gestión de Usuarios | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar app-navbar sticky-top py-2 py-md-3">
        <div class="container d-flex align-items-center justify-content-between gap-2">
            <a class="navbar-brand d-flex align-items-center gap-2 m-0" href="admin.php">
                <span class="marca-icono" aria-hidden="true">CS</span>
                <span class="fw-bold">Control Stock</span>
            </a>
            <div class="d-flex gap-2">
                <a href="admin.php" class="btn btn-outline-secondary btn-sm">Volver</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Salir</a>
            </div>
        </div>
    </nav>

    <main class="container py-3 py-md-5">
        <div class="mb-4">
            <p class="etiqueta text-primary mb-1">Administración de Cuentas</p>
            <h1 class="h2 fw-bold mb-2">Gestión de Usuarios (Admin y Vendedor)</h1>
            <p class="texto-secundario mb-0">Control de personal de ventas, administradores y límites de descuento autorizados.</p>
        </div>

        <?php if (isset($_GET["creado"])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="status">
                El usuario fue registrado correctamente con su contraseña encriptada.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET["limite_actualizado"])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="status">
                El límite de descuento del vendedor fue actualizado exitosamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
        <?php if ($error !== ""): ?>
            <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="row g-4 align-items-start">
            <!-- Formulario de Alta de Usuario -->
            <div class="col-12 col-lg-4">
                <section class="seccion-card" aria-labelledby="titulo-crear-usuario">
                    <h2 id="titulo-crear-usuario" class="h5 fw-bold mb-1">Registrar cuenta</h2>
                    <p class="texto-secundario small mb-4">Completá los datos para crear un nuevo usuario con rol de Administrador o Vendedor.</p>
                    <form method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_crear_usuario"]) ?>">
                        <input type="hidden" name="accion" value="crear_usuario">
                        
                        <div class="mb-3">
                            <label for="dni" class="form-label">DNI *</label>
                            <input type="text" id="dni" name="dni" class="form-control" inputmode="numeric" maxlength="20" placeholder="Ej: 42123456" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-sm-6">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" id="nombre" name="nombre" class="form-control" maxlength="100" placeholder="Ej: Juan" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="apellido" class="form-label">Apellido *</label>
                                <input type="text" id="apellido" name="apellido" class="form-control" maxlength="100" placeholder="Ej: Pérez" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña inicial *</label>
                            <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" placeholder="••••••••" required>
                        </div>
                        <div class="mb-3">
                            <label for="rol" class="form-label">Tipo de cuenta / Rol *</label>
                            <select id="rol" name="rol" class="form-select" required>
                                <option value="vendedor" selected>Vendedor (Punto de Venta)</option>
                                <option value="admin">Administrador (Control total)</option>
                            </select>
                        </div>

                        <!-- Límite de Descuento para Vendedores -->
                        <div class="mb-4" id="contenedorLimiteDescuento">
                            <label for="limiteDescuento" class="form-label">Límite de descuento máximo permitido (%)</label>
                            <select id="limiteDescuento" name="limite_descuento" class="form-select">
                                <option value="0">0% (Sin descuento permitido)</option>
                                <option value="5">5% de descuento máximo</option>
                                <option value="10">10% de descuento máximo</option>
                                <option value="15" selected>15% de descuento máximo (Estándar)</option>
                                <option value="20">20% de descuento máximo</option>
                                <option value="25">25% de descuento máximo</option>
                                <option value="50">50% de descuento máximo</option>
                                <option value="100">100% (Sin límite de descuento)</option>
                            </select>
                            <small class="text-muted">El vendedor no podrá otorgar descuentos mayores a este tope.</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">+ Guardar usuario</button>
                    </form>
                </section>
            </div>

            <!-- Listado Segmentado por Roles con Buscador -->
            <div class="col-12 col-lg-8">
                <section class="seccion-card" aria-labelledby="titulo-segmentacion-usuarios">
                    
                    <!-- Barra de Búsqueda Instantánea -->
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h2 id="titulo-segmentacion-usuarios" class="h5 fw-bold text-dark mb-0">Personal Autorizado (<?= count($equipo) ?>)</h2>
                        <div class="flex-grow-1" style="max-width: 320px;">
                            <input type="text" id="buscadorUsuarios" class="form-control form-control-sm" placeholder="Buscar por nombre, apellido, DNI...">
                        </div>
                    </div>

                    <?php if (empty($equipo)): ?>
                        <div class="empty-state">No hay miembros registrados en el sistema.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 tabla-filtrable">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>DNI</th>
                                        <th>Rol</th>
                                        <th>Límite Descuento</th>
                                        <th class="text-end">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($equipo as $miembro): ?>
                                    <?php $limiteActual = isset($miembro["limite_descuento"]) ? (float)$miembro["limite_descuento"] : (($miembro["rol"] === "vendedor") ? 15.0 : 100.0); ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="avatar bg-primary-subtle text-primary" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($miembro["nombre"], 0, 1))) ?></span>
                                                <div>
                                                    <div class="fw-semibold nombre-usuario"><?= e(trim($miembro["nombre"] . " " . ($miembro["apellido"] ?? ""))) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="dni-usuario font-monospace small"><?= e($miembro["dni"]) ?></td>
                                        <td>
                                            <?php if ($miembro["rol"] === "admin"): ?>
                                                <span class="badge text-bg-danger rol-usuario">Administrador</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-primary rol-usuario">Vendedor</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($miembro["rol"] === "admin"): ?>
                                                <span class="badge text-bg-light border">Sin límite (100%)</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-warning text-dark fw-bold">Máx. <?= $limiteActual ?>%</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($miembro["rol"] === "vendedor"): ?>
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="abrirModalEditarLimite(<?= (int)$miembro['id'] ?>, '<?= e(addslashes($miembro['nombre'])) ?>', <?= $limiteActual ?>)">
                                                    Ajustar Límite
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>

    <!-- Modal Ajustar Límite de Descuento -->
    <div class="modal fade" id="modalEditarLimite" tabindex="-1" aria-labelledby="tituloModalLimite" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_crear_usuario"]) ?>">
                    <input type="hidden" name="accion" value="actualizar_limite">
                    <input type="hidden" name="usuario_id" id="modalLimiteUserId">
                    
                    <div class="modal-header">
                        <h2 id="tituloModalLimite" class="modal-title fs-5 fw-bold">Ajustar Límite de Descuento</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="texto-secundario small mb-3">Establecé el porcentaje máximo de descuento que el vendedor <strong id="modalLimiteUserName"></strong> puede aplicar en el mostrador de ventas.</p>
                        
                        <div class="mb-3">
                            <label for="modalLimiteInput" class="form-label">Porcentaje Máximo Permitido (%)</label>
                            <select id="modalLimiteInput" name="limite_descuento" class="form-select">
                                <option value="0">0% (Prohibido hacer descuentos)</option>
                                <option value="5">5% de descuento máximo</option>
                                <option value="10">10% de descuento máximo</option>
                                <option value="15">15% de descuento máximo (Estándar)</option>
                                <option value="20">20% de descuento máximo</option>
                                <option value="25">25% de descuento máximo</option>
                                <option value="30">30% de descuento máximo</option>
                                <option value="50">50% de descuento máximo</option>
                                <option value="100">100% (Sin límite)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Límite</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Visibilidad dinámica del selector de límite de descuento en el formulario de alta
        const selectRol = document.getElementById("rol");
        const contLimite = document.getElementById("contenedorLimiteDescuento");
        if (selectRol && contLimite) {
            selectRol.addEventListener("change", (e) => {
                if (e.target.value === "vendedor") {
                    contLimite.classList.remove("d-none");
                } else {
                    contLimite.classList.add("d-none");
                }
            });
        }

        function abrirModalEditarLimite(userId, userName, limiteActual) {
            document.getElementById("modalLimiteUserId").value = userId;
            document.getElementById("modalLimiteUserName").textContent = userName;
            document.getElementById("modalLimiteInput").value = limiteActual;
            bootstrap.Modal.getOrCreateInstance(document.getElementById("modalEditarLimite")).show();
        }

        const buscador = document.getElementById("buscadorUsuarios");
        if (buscador) {
            buscador.addEventListener("input", (evento) => {
                const query = evento.target.value.toLowerCase().trim();
                const tablas = document.querySelectorAll(".tabla-filtrable tbody");
                tablas.forEach((tbody) => {
                    const filas = tbody.querySelectorAll("tr");
                    filas.forEach((fila) => {
                        const texto = fila.textContent.toLowerCase();
                        fila.style.display = texto.includes(query) ? "" : "none";
                    });
                });
            });
        }
    </script>
</body>
</html>
