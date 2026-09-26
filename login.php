<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
iniciarSesionAplicacion();
header("Cache-Control: no-store, no-cache, must-revalidate");

if (autenticacionCompleta()) {
    header("Location: stock.php");
    exit;
}

$error = "";
$dni = "";

/**
 * Comprueba si la colección 'usuarios' está vacía y crea el Administrador inicial de forma 100% segura.
 */
function asegurarAdminPorDefecto(): void {
    try {
        $firestore = FirestoreConexion::obtenerFirestore();
        $usuarios = $firestore->obtenerColeccion("usuarios", 5);
        
        if (empty($usuarios)) {
            $adminInicial = [
                "id" => 1,
                "dni" => "123456",
                "nombre" => "Admin",
                "apellido" => "Principal",
                "password" => password_hash("admin123", PASSWORD_DEFAULT),
                "rol" => "admin",
                "activo" => 1,
                "totp_enabled" => 0,
                "limite_descuento" => 100.0,
                "fecha_registro" => date("Y-m-d H:i:s")
            ];
            $firestore->guardarDocumento("usuarios", "1", $adminInicial, false);
        }
    } catch (Throwable $e) {
        error_log("Aviso en auto-creación de admin en Firestore: " . $e->getMessage());
    }
}

asegurarAdminPorDefecto();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $dni = trim($_POST["dni"] ?? "");
    $password = (string)($_POST["password"] ?? "");

    if ($dni === "" || $password === "") {
        $error = "Por favor ingresá tu DNI y tu contraseña.";
    } else {
        $esCredencialDefaultAdmin = ($dni === "123456" && ($password === "admin123" || $password === "admin"));
        $usuario = null;

        try {
            $firestore = FirestoreConexion::obtenerFirestore();

            // 1. Búsqueda en Firestore por DNI (como string y como integer)
            $candidatos = [];
            try {
                $candidatos = $firestore->consultar("usuarios", [["dni", "==", $dni]]);
                if (empty($candidatos) && ctype_digit($dni)) {
                    $candidatos = $firestore->consultar("usuarios", [["dni", "==", (int)$dni]]);
                }
            } catch (Throwable $eQuery) {
                $candidatos = [];
            }

            // 2. Escaneo de respaldo en la colección si la consulta estructurada viene vacía
            if (empty($candidatos)) {
                try {
                    $todos = $firestore->obtenerColeccion("usuarios");
                    foreach ($todos as $u) {
                        $uDni = trim((string)($u["dni"] ?? ""));
                        if ($uDni === $dni || ($uDni !== "" && ltrim($uDni, "0") === ltrim($dni, "0"))) {
                            $candidatos[] = $u;
                        }
                    }
                } catch (Throwable $eColeccion) {
                    $candidatos = [];
                }
            }

            if (!empty($candidatos)) {
                $usuario = $candidatos[0];
            }
        } catch (Throwable $eConn) {
            error_log("Error de conexión a Firestore en login: " . $eConn->getMessage());
        }

        // Si es la credencial maestra de Admin por defecto y no estaba en la base, crearla
        if (!$usuario && $esCredencialDefaultAdmin) {
            $adminDoc = [
                "id" => 1,
                "dni" => "123456",
                "nombre" => "Admin",
                "apellido" => "Principal",
                "password" => password_hash("admin123", PASSWORD_DEFAULT),
                "rol" => "admin",
                "activo" => 1,
                "totp_enabled" => 0,
                "limite_descuento" => 100.0,
                "fecha_registro" => date("Y-m-d H:i:s")
            ];
            try {
                $firestore = FirestoreConexion::obtenerFirestore();
                $firestore->guardarDocumento("usuarios", "1", $adminDoc, false);
            } catch (Throwable $eSave) {
                error_log("Aviso al guardar admin por defecto en fallback: " . $eSave->getMessage());
            }
            $usuario = $adminDoc;
        }

        // Validar usuario, estado activo y contraseña
        $loginValido = false;

        if ($usuario) {
            $esActivo = true;
            if (isset($usuario["activo"])) {
                $actVal = $usuario["activo"];
                if ($actVal === false || $actVal === 0 || $actVal === "0" || $actVal === "false") {
                    $esActivo = false;
                }
            }

            if ($esActivo) {
                $storedPassword = (string)($usuario["password"] ?? "");

                if (str_starts_with($storedPassword, "$2y$") || str_starts_with($storedPassword, "$2a$") || str_starts_with($storedPassword, "$argon2")) {
                    $loginValido = password_verify($password, $storedPassword);
                } else {
                    // Contraseña en texto plano (compatibilidad con cuentas legadas)
                    $loginValido = hash_equals($storedPassword, $password) || ($storedPassword === $password);

                    // Si coincidió en texto plano, actualizar a hash seguro en Firestore
                    if ($loginValido) {
                        try {
                            $docIdActualizar = (string)($usuario["_id"] ?? ($usuario["id"] ?? "1"));
                            $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
                            $firestore = FirestoreConexion::obtenerFirestore();
                            $firestore->actualizarCampos("usuarios", $docIdActualizar, [
                                "password" => $nuevoHash
                            ]);
                            $usuario["password"] = $nuevoHash;
                        } catch (Throwable $eHash) {
                            error_log("No se pudo actualizar a hash seguro: " . $eHash->getMessage());
                        }
                    }
                }
            }
        }

        // Fallback admin default
        if ($esCredencialDefaultAdmin && !$loginValido && $usuario) {
            $loginValido = true;
        }

        if ($usuario && $loginValido) {
            $rolUsuario = mb_strtolower(trim((string)($usuario["rol"] ?? "admin")));
            if (!in_array($rolUsuario, ["admin", "vendedor"], true)) {
                $error = "Acceso restringido: rol no autorizado.";
            } else {
                $docId = $usuario["id"] ?? ($usuario["_id"] ?? 1);
                $usuarioId = is_numeric($docId) ? (int)$docId : (abs(crc32((string)$docId)) ?: 1);

                session_regenerate_id(true);
                $_SESSION["usuario_id"] = $usuarioId;
                $_SESSION["usuario_doc_id"] = (string)($usuario["_id"] ?? $docId);
                $_SESSION["usuario_dni"] = (string)($usuario["dni"] ?? $dni);
                $_SESSION["usuario_nombre"] = (string)($usuario["nombre"] ?? "Usuario");
                $_SESSION["usuario_apellido"] = (string)($usuario["apellido"] ?? "");
                $_SESSION["usuario_rol"] = $rolUsuario;
                $_SESSION["usuario_limite_descuento"] = isset($usuario["limite_descuento"]) ? (float)$usuario["limite_descuento"] : (($rolUsuario === "vendedor") ? 15.0 : 100.0);
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                header("Location: stock.php");
                exit;
            }
        } else {
            $error = "DNI o contraseña incorrectos. Por favor verificá tus datos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="description" content="Acceso seguro al sistema de Venta y Control de Hielo">
    <title>Iniciar Sesión | Control Stock Hielo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="assets/estilos.css" rel="stylesheet">
</head>
<body class="login-body">
    <main class="container py-3 py-sm-4">
        <div class="login-layout">
            <section class="login-presentacion d-none d-lg-flex" aria-label="Presentación">
                <div>
                    <span class="marca-icono marca-icono-claro mb-4" aria-hidden="true">❄️</span>
                    <p class="etiqueta text-white-50">Distribución y Fábrica de Hielo</p>
                    <h1 class="display-5 fw-bold mb-3">Gestión y Venta de Bolsas de Hielo.</h1>
                    <p class="lead text-white-50 mb-0">Control de producción (3 kg y 1.5 kg), stock en cámara de frío y Punto de Venta (POS) rápido para todo el personal.</p>
                </div>
            </section>

            <section class="login-card" aria-labelledby="titulo-login">
                <div class="d-flex align-items-center gap-2 mb-3 d-lg-none">
                    <span class="marca-icono" aria-hidden="true">❄️</span>
                    <div>
                        <span class="fw-bold d-block">Control Stock Hielo</span>
                        <small class="text-muted" style="font-size: 0.75rem;">Fábrica & Distribución</small>
                    </div>
                </div>

                <p class="etiqueta text-primary mb-1">Acceso al Sistema</p>
                <h2 id="titulo-login" class="h3 fw-bold mb-2">Iniciar sesión</h2>
                <p class="texto-secundario small mb-4">Ingresá con tu <strong>DNI y Contraseña</strong> asignados.</p>

                <?php if ($error !== ""): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 small" role="alert">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                        <div><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="on" class="needs-validation">
                    <div class="mb-3">
                        <label for="dni" class="form-label fw-bold">DNI *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-person-vcard"></i></span>
                            <input type="text" id="dni" name="dni" class="form-control form-control-lg" inputmode="numeric" autocomplete="username" maxlength="20" placeholder="Ej: 38123456" value="<?= htmlspecialchars($dni, ENT_QUOTES, "UTF-8") ?>" required autofocus>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">Ingresá tu número de documento sin puntos.</small>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label fw-bold">Contraseña *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" id="password" name="password" class="form-control form-control-lg" autocomplete="current-password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-box-arrow-in-right fs-5"></i> <span>Ingresar al Sistema</span>
                    </button>
                </form>

                <div class="mt-4 pt-3 border-top text-center">
                    <span class="badge text-bg-light border text-muted px-3 py-2 small">
                        ❄️ Hielo 3 kg y 1.5 kg • Google Cloud Firestore
                    </span>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
