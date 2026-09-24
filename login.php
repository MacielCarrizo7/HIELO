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
$nombre = "";
$apellido = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $dni = trim($_POST["dni"] ?? "");
    $nombre = trim($_POST["nombre"] ?? "");
    $apellido = trim($_POST["apellido"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($dni === "" || $nombre === "" || $apellido === "" || $password === "") {
        $error = "Completá todos los campos para ingresar.";
    } else {
        try {
            $firestore = FirestoreConexion::obtenerFirestore();

            // 1. Búsqueda de usuario por DNI en Firestore (tolerante a string e integer)
            $candidatos = [];
            try {
                $candidatos = $firestore->consultar("usuarios", [
                    ["dni", "==", $dni]
                ]);
                if (empty($candidatos) && ctype_digit($dni)) {
                    $candidatos = $firestore->consultar("usuarios", [
                        ["dni", "==", (int)$dni]
                    ]);
                }
            } catch (Throwable $eQuery) {
                $candidatos = [];
            }

            // Si la consulta estructurada no arrojó resultados, escanear la colección usuarios
            if (empty($candidatos)) {
                $todosUsuarios = $firestore->obtenerColeccion("usuarios");
                foreach ($todosUsuarios as $u) {
                    $uDni = trim((string)($u["dni"] ?? ""));
                    if ($uDni === $dni) {
                        $candidatos[] = $u;
                    }
                }
            }

            // 2. Filtrar candidato por Nombre y Apellido (insensible a mayúsculas/minúsculas)
            $usuario = null;
            $nombreLower = mb_strtolower($nombre);
            $apellidoLower = mb_strtolower($apellido);

            foreach ($candidatos as $c) {
                $nomDoc = mb_strtolower(trim((string)($c["nombre"] ?? "")));
                $apeDoc = mb_strtolower(trim((string)($c["apellido"] ?? "")));

                $matchNom = ($nomDoc === "" || $nomDoc === $nombreLower);
                $matchApe = ($apeDoc === "" || $apeDoc === $apellidoLower);

                if ($matchNom && $matchApe) {
                    $usuario = $c;
                    break;
                }
            }

            // Si solo hay un usuario con ese DNI, permitir coincidencia de nombre flexible
            if (!$usuario && count($candidatos) === 1) {
                $c = $candidatos[0];
                $nomDoc = mb_strtolower(trim((string)($c["nombre"] ?? "")));
                if ($nomDoc === "" || $nomDoc === $nombreLower) {
                    $usuario = $c;
                }
            }

            // 3. Validar estado activo
            $esActivo = true;
            if ($usuario && isset($usuario["activo"])) {
                $actVal = $usuario["activo"];
                if ($actVal === false || $actVal === 0 || $actVal === "0" || $actVal === "false") {
                    $esActivo = false;
                }
            }

            // 4. Validación de contraseña (Compatible con hash Bcrypt y Texto Plano)
            $passwordValido = false;
            if ($usuario && $esActivo) {
                $storedPassword = (string)($usuario["password"] ?? "");

                if (str_starts_with($storedPassword, "$2y$") || str_starts_with($storedPassword, "$2a$") || str_starts_with($storedPassword, "$argon2")) {
                    $passwordValido = password_verify($password, $storedPassword);
                } else {
                    // Contraseña en texto plano creada manualmente en Firebase Firestore
                    $passwordValido = hash_equals($storedPassword, $password) || ($storedPassword === $password);

                    // Si coincidió en texto plano, actualizar automáticamente a hash seguro Bcrypt en Firestore
                    if ($passwordValido) {
                        $docIdActualizar = (string)($usuario["_id"] ?? ($usuario["id"] ?? ""));
                        if ($docIdActualizar !== "") {
                            try {
                                $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
                                $firestore->actualizarCampos("usuarios", $docIdActualizar, [
                                    "password" => $nuevoHash,
                                    "apellido" => !empty($usuario["apellido"]) ? $usuario["apellido"] : $apellido
                                ]);
                                $usuario["password"] = $nuevoHash;
                            } catch (Throwable $eHash) {
                                error_log("No se pudo actualizar a hash seguro: " . $eHash->getMessage());
                            }
                        }
                    }
                }
            }

            // 5. Iniciar sesión si las credenciales son válidas
            if ($usuario && $esActivo && $passwordValido) {
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
                    $_SESSION["usuario_nombre"] = (string)($usuario["nombre"] ?? $nombre);
                    $_SESSION["usuario_apellido"] = (string)(!empty($usuario["apellido"]) ? $usuario["apellido"] : $apellido);
                    $_SESSION["usuario_rol"] = $rolUsuario;
                    $_SESSION["usuario_limite_descuento"] = isset($usuario["limite_descuento"]) ? (float)$usuario["limite_descuento"] : (($rolUsuario === "vendedor") ? 15.0 : 100.0);
                    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                    header("Location: stock.php");
                    exit;
                }
            } else {
                $error = "Los datos ingresados no son correctos.";
            }
        } catch (Throwable $e) {
            error_log("Error en login: " . $e->getMessage());
            $error = "Los datos ingresados no son correctos o hubo un problema de conexión.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Acceso seguro al sistema Control Stock">
    <title>Iniciar sesión | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
</head>
<body class="login-body">
    <main class="container py-4">
        <div class="login-layout">
            <section class="login-presentacion d-none d-lg-flex" aria-label="Presentación">
                <div>
                    <span class="marca-icono marca-icono-claro mb-4" aria-hidden="true">CS</span>
                    <p class="etiqueta text-white-50">Gestión simple y segura</p>
                    <h1 class="display-5 fw-bold mb-3">Todo tu negocio en un solo lugar.</h1>
                    <p class="lead text-white-50 mb-0">Administrá productos, inventario y ventas con una experiencia ágil y robusta.</p>
                </div>
            </section>

            <section class="login-card" aria-labelledby="titulo-login">
                <div class="d-flex align-items-center gap-2 mb-4 d-lg-none">
                    <span class="marca-icono" aria-hidden="true">CS</span>
                    <span class="fw-bold">Control Stock</span>
                </div>
                <p class="etiqueta text-primary mb-2">Bienvenido</p>
                <h2 id="titulo-login" class="h2 fw-bold mb-2">Iniciar sesión</h2>
                <p class="texto-secundario mb-4">Ingresá tus 4 datos de autenticación para acceder al sistema.</p>

                <?php if ($error !== ""): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="on">
                    <div class="mb-3">
                        <label for="dni" class="form-label">DNI *</label>
                        <input type="text" id="dni" name="dni" class="form-control" inputmode="numeric" autocomplete="username" maxlength="20" placeholder="Ej: 12345678" value="<?= htmlspecialchars($dni, ENT_QUOTES, "UTF-8") ?>" required autofocus>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" id="nombre" name="nombre" class="form-control" autocomplete="given-name" maxlength="100" placeholder="Ej: Admin" value="<?= htmlspecialchars($nombre, ENT_QUOTES, "UTF-8") ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="apellido" class="form-label">Apellido *</label>
                            <input type="text" id="apellido" name="apellido" class="form-control" autocomplete="family-name" maxlength="100" placeholder="Ej: General" value="<?= htmlspecialchars($apellido, ENT_QUOTES, "UTF-8") ?>" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Contraseña *</label>
                        <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">Iniciar sesión</button>
                </form>

                <p class="texto-secundario small text-center mt-4 mb-0">Sistema 100% Cloud con PHP 8.2, Docker y Firebase Firestore en Render.</p>
            </section>
        </div>
    </main>
</body>
</html>
