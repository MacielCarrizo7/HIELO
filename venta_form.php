<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin", "vendedor"]);
require_once __DIR__ . "/FirestoreConexion.php";

$rol = $_SESSION["usuario_rol"] ?? "vendedor";
$esAdmin = ($rol === "admin");
$limiteDescuento = $esAdmin ? 100 : (int)($_SESSION["usuario_limite_descuento"] ?? 15);
$nombreCompleto = trim(($_SESSION["usuario_nombre"] ?? "Usuario") . " " . ($_SESSION["usuario_apellido"] ?? ""));
$paginaRetorno = $esAdmin ? "admin.php" : "vendedor.php";
$csrf = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta (POS) - Fábrica de Hielo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
    <style>
        :root {
            --pos-font-heading: 'Outfit', sans-serif;
            --pos-font-body: 'Inter', sans-serif;
        }

        body {
            background-color: #f1f5f9;
            font-family: var(--pos-font-body);
        }

        .pos-header-title {
            font-family: var(--pos-font-heading);
            font-weight: 800;
        }

        /* Cuadrados / Tarjetas de Productos de Hielo */
        .grid-pos-productos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 10px;
        }

        @media (min-width: 576px) {
            .grid-pos-productos {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 12px;
            }
        }

        .ice-product-tile {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 12px 10px;
            text-align: center;
            cursor: pointer;
            user-select: none;
            transition: all 0.15s ease-in-out;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 125px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            position: relative;
        }

        .ice-product-tile:hover {
            border-color: #0284c7;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(2, 132, 199, 0.15);
        }

        .ice-product-tile.active-tile {
            border-color: #0284c7;
            background: #f0f9ff;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.25), 0 8px 20px rgba(2, 132, 199, 0.15);
            transform: scale(1.02);
        }

        .ice-product-tile.agotado-tile {
            opacity: 0.55;
            background: #f8fafc;
            border-color: #cbd5e1;
            cursor: not-allowed;
        }

        .ice-icon-box {
            font-size: 1.6rem;
            line-height: 1;
            margin-bottom: 4px;
        }

        .ice-tile-name {
            font-family: var(--pos-font-heading);
            font-size: 0.88rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 4px;
        }

        .ice-tile-price {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0284c7;
        }

        .ice-tile-stock {
            font-size: 0.68rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 6px;
            display: inline-block;
        }

        /* Panel de Configuración Rápida del Producto Seleccionado */
        .panel-config-item {
            background: #ffffff;
            border: 1px solid #bae6fd;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.08);
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stepper-btn {
            width: 44px;
            height: 44px;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        .input-qty-box {
            height: 44px;
            font-size: 1.25rem;
            font-weight: 800;
            text-align: center;
            border-radius: 10px;
        }

        /* Panel Ticket / Carrito */
        .ticket-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        }

        .ticket-item-row {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 12px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ticket-total-box {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: 14px;
            padding: 14px 16px;
        }
    </style>
</head>
<body data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, "UTF-8") ?>" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, "UTF-8") ?>" data-limite-descuento="<?= $limiteDescuento ?>">

    <!-- Barra Superior Limpia -->
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top py-2 shadow-xs">
        <div class="container-fluid px-3 px-md-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <a href="<?= $paginaRetorno ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> <span>Volver</span>
                </a>
                <span class="fs-4">❄️</span>
                <span class="fw-bold pos-header-title text-dark">Punto de Venta (POS)</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-light border text-muted small d-none d-sm-inline">
                    <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($nombreCompleto, ENT_QUOTES, "UTF-8") ?>
                </span>
            </div>
        </div>
    </nav>

    <main class="container-fluid px-3 px-md-4 py-3">
        
        <!-- Alertas del Sistema -->
        <div id="alertaError" class="alert alert-danger d-none mb-3" role="alert"></div>
        <div id="alertaExito" class="alert alert-success d-none mb-3" role="alert"></div>

        <div class="row g-3">
            
            <!-- =======================================================
                 COLUMNA IZQUIERDA: CATÁLOGO VISUAL Y SELECTOR RÁPIDO
            ======================================================== -->
            <div class="col-12 col-lg-7">
                
                <!-- 1. Cuadrados de Productos de Hielo -->
                <div class="bg-white border rounded-4 p-3 mb-3 shadow-xs">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <span class="text-primary">🧊</span> <span>Selecciona la Bolsa de Hielo</span>
                        </h2>
                        <span class="badge text-bg-light border text-muted small" id="contadorProductosCargados">Cargando...</span>
                    </div>

                    <!-- Grid de Tarjetas de Hielo -->
                    <div class="grid-pos-productos" id="gridProductosHielo">
                        <div class="text-center text-muted py-4 w-100">
                            <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                            Cargando presentaciones de hielo...
                        </div>
                    </div>
                </div>

                <!-- 2. Panel de Configuración Directa de Cantidad y Descuento -->
                <div id="panelConfiguradorProducto" class="panel-config-item d-none">
                    <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom">
                        <div>
                            <span class="badge bg-primary bg-opacity-10 text-primary mb-1" id="cfgProdCat">Hielo en Cubos</span>
                            <h3 class="h5 fw-bold text-dark mb-0" id="cfgProdNombre">Bolsa de Hielo</h3>
                            <small class="text-muted" id="cfgProdStock">Stock disponible: 0 un.</small>
                        </div>
                        <div class="text-end">
                            <span class="fs-4 fw-bold text-primary" id="cfgProdPrecioLista">$ 0</span>
                            <small class="text-muted d-block">precio de lista</small>
                        </div>
                    </div>

                    <!-- Controles de Cantidad y Descuento -->
                    <div class="row g-3 align-items-center mb-3">
                        
                        <!-- Cantidad -->
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1">Cantidad de Bolsas</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-secondary stepper-btn" id="btnRestarCantidad">-</button>
                                <input type="number" min="1" class="form-control input-qty-box" id="inputCantidadPOS" value="1">
                                <button type="button" class="btn btn-outline-secondary stepper-btn" id="btnSumarCantidad">+</button>
                            </div>
                            <!-- Botones rápidos de cantidad -->
                            <div class="d-flex gap-1 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-light border text-dark fw-semibold px-2 py-0 btn-preset-cant" data-cant="1">+1</button>
                                <button type="button" class="btn btn-sm btn-outline-light border text-dark fw-semibold px-2 py-0 btn-preset-cant" data-cant="5">+5</button>
                                <button type="button" class="btn btn-sm btn-outline-light border text-dark fw-semibold px-2 py-0 btn-preset-cant" data-cant="10">+10</button>
                                <button type="button" class="btn btn-sm btn-outline-light border text-dark fw-semibold px-2 py-0 btn-preset-cant" data-cant="20">+20</button>
                                <button type="button" class="btn btn-sm btn-outline-light border text-dark fw-semibold px-2 py-0 btn-preset-cant" data-cant="50">+50</button>
                            </div>
                        </div>

                        <!-- Descuento -->
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1">Descuento (%)</label>
                            <select class="form-select form-select-lg fw-bold" id="selectDescuentoPOS">
                                <option value="0" selected>Sin descuento (0%)</option>
                                <option value="5">5% de descuento</option>
                                <option value="10">10% de descuento</option>
                                <option value="15">15% de descuento</option>
                                <?php if ($esAdmin): ?>
                                    <option value="20">20% de descuento</option>
                                    <option value="25">25% de descuento</option>
                                    <option value="30">30% de descuento</option>
                                    <option value="custom">Personalizado...</option>
                                <?php endif; ?>
                            </select>
                            <input type="number" min="0" max="<?= $limiteDescuento ?>" class="form-control mt-1 d-none font-monospace fw-bold" id="inputDescuentoCustomPOS" placeholder="Porcentaje personalizado (máx <?= $limiteDescuento ?>%)">
                        </div>

                    </div>

                    <!-- Resumen del Ítem y Botón de Agregar -->
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 pt-2 border-top">
                        <div>
                            <div class="small text-muted">
                                Unitario c/desc: <strong class="text-dark" id="cfgItemUnitarioFinal">$ 0</strong>
                            </div>
                            <div class="fs-5 fw-bold text-dark">
                                Subtotal ítem: <span class="text-primary" id="cfgItemTotalFinal">$ 0</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary btn-lg fw-bold px-4 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnAgregarAlTicket">
                            <i class="bi bi-cart-plus-fill fs-5"></i>
                            <span>Agregar al Ticket</span>
                        </button>
                    </div>

                </div>

            </div>

            <!-- =======================================================
                 COLUMNA DERECHA: TICKET DE COMPRA Y COBRO
            ======================================================== -->
            <div class="col-12 col-lg-5">
                
                <div class="ticket-card">
                    
                    <!-- Encabezado Ticket -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-receipt fs-4 text-primary"></i>
                            <h2 class="h5 fw-bold text-dark mb-0">Ticket de Venta</h2>
                        </div>
                        <span class="badge bg-primary text-white fs-6 px-3 py-1" id="ticketBadgeCount">0 bolsas</span>
                    </div>

                    <!-- Datos Rápidos del Cliente (Acordeón Simple) -->
                    <div class="bg-light border rounded-3 p-2 mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="small">
                                <span class="text-muted">Cliente:</span>
                                <strong class="text-dark d-block" id="lblClienteActivo">Consumidor Final</strong>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" id="btnToggleDatosCliente">
                                <i class="bi bi-pencil-square me-1"></i> Cambiar
                            </button>
                        </div>
                        
                        <!-- Formulario Desplegable de Cliente -->
                        <div id="cajaDatosCliente" class="mt-2 pt-2 border-top d-none">
                            <input type="text" class="form-control form-control-sm mb-1" id="posClienteNombre" value="Consumidor Final" placeholder="Nombre / Razón Social *">
                            <input type="tel" class="form-control form-control-sm mb-1" id="posClienteTelefono" placeholder="Teléfono / WhatsApp (opcional)">
                            <input type="text" class="form-control form-control-sm" id="posClienteDireccion" placeholder="Dirección de entrega (opcional)">
                        </div>
                    </div>

                    <!-- Lista de Ítems del Ticket -->
                    <div id="cajaItemsTicket" class="mb-3" style="max-height: 280px; overflow-y: auto;">
                        <div class="text-center text-muted py-4 bg-light rounded-3" id="ticketVacioMsg">
                            <i class="bi bi-cart-x fs-2 d-block text-muted mb-1"></i>
                            El ticket está vacío.<br>Toca un producto de hielo a la izquierda para cargarlo.
                        </div>
                    </div>

                    <!-- Total a Cobrar -->
                    <div class="ticket-total-box mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1 text-white-50 small">
                            <span>Subtotal bolsas:</span>
                            <span id="ticketSubtotalBruto">$ 0</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 text-warning small" id="cajaFilaDescuentoTotal">
                            <span>Descuentos:</span>
                            <span id="ticketDescuentoMonto">-$ 0</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary">
                            <span class="fw-bold fs-6">TOTAL A COBRAR:</span>
                            <span class="fs-3 fw-bold text-success" id="ticketTotalPagar">$ 0</span>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="d-flex flex-column gap-2">
                        <button type="button" class="btn btn-success btn-lg fw-bold py-3 fs-5 shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnConfirmarCobroVenta">
                            <i class="bi bi-check-circle-fill fs-4"></i> Confirmar y Cobrar Venta
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnVaciarTicket">
                            <i class="bi bi-trash me-1"></i> Vaciar Ticket
                        </button>
                    </div>

                </div>

            </div>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const formatoMoneda = new Intl.NumberFormat("es-AR", { style: "currency", currency: "ARS", maximumFractionDigits: 0 });
        const csrfToken = document.body.dataset.csrf;
        const rolUsuario = document.body.dataset.rol;
        const limiteDescuento = Number(document.body.dataset.limiteDescuento) || 15;

        let productosPOS = [];
        let productoSeleccionado = null;
        let ticketItems = [];

        // 1. Cargar Catálogo de Productos
        async function cargarProductosPOS() {
            const grid = document.getElementById("gridProductosHielo");
            const contador = document.getElementById("contadorProductosCargados");
            try {
                const resp = await fetch("obtener_productos.php");
                productosPOS = await resp.json().catch(() => []);

                if (!Array.isArray(productosPOS) || productosPOS.length === 0) {
                    grid.innerHTML = '<div class="col-12 text-center text-muted py-4">No hay presentaciones de hielo cargadas en el sistema.</div>';
                    if (contador) contador.textContent = "0 productos";
                    return;
                }

                if (contador) contador.textContent = `${productosPOS.length} presentaciones`;
                renderizarGridProductos();

                // Seleccionar automáticamente el primer producto con stock
                const primeroConStock = productosPOS.find(p => Number(p.stock) > 0) || productosPOS[0];
                if (primeroConStock) {
                    seleccionarProductoTile(primeroConStock);
                }

            } catch (err) {
                console.error(err);
                grid.innerHTML = `<div class="col-12 text-center text-danger py-4">Error al cargar productos: ${err.message}</div>`;
            }
        }

        // 2. Renderizar Cuadrados / Tarjetas de Hielo
        function renderizarGridProductos() {
            const grid = document.getElementById("gridProductosHielo");
            grid.replaceChildren();

            productosPOS.forEach(p => {
                const stockNum = Number(p.stock) || 0;
                const precio = Number(p.precio_venta || p.precio) || 0;
                const esAgotado = stockNum <= 0;

                const tile = document.createElement("div");
                tile.className = `ice-product-tile ${esAgotado ? 'agotado-tile' : ''}`;
                tile.id = `tileProd_${p.id}`;

                // Icono según tipo de hielo
                let icono = "🧊";
                const nomLower = (p.nombre || "").toLowerCase();
                if (nomLower.includes("barra") || nomLower.includes("bloque")) icono = "🧊";
                else if (nomLower.includes("rollo") || nomLower.includes("cilindro")) icono = "❄️";
                else if (nomLower.includes("escarcha") || nomLower.includes("picado")) icono = "🏔️";

                const stockBadgeClass = stockNum <= 0 ? 'bg-danger text-white' : stockNum <= 5 ? 'bg-warning text-dark' : 'bg-light text-muted border';

                tile.innerHTML = `
                    <div>
                        <div class="ice-icon-box">${icono}</div>
                        <div class="ice-tile-name text-truncate" title="${escapeHtml(p.nombre)}">${escapeHtml(p.nombre)}</div>
                    </div>
                    <div>
                        <div class="ice-tile-price">${formatoMoneda.format(precio)}</div>
                        <span class="ice-tile-stock ${stockBadgeClass}">${stockNum <= 0 ? 'Agotado' : stockNum + ' un.'}</span>
                    </div>
                `;

                if (!esAgotado) {
                    tile.addEventListener("click", () => {
                        seleccionarProductoTile(p);
                    });
                }

                grid.appendChild(tile);
            });
        }

        // 3. Seleccionar Producto
        function seleccionarProductoTile(prod) {
            productoSeleccionado = prod;

            // Actualizar estilo visual activo en las tarjetas
            document.querySelectorAll(".ice-product-tile").forEach(t => t.classList.remove("active-tile"));
            const tileActivo = document.getElementById(`tileProd_${prod.id}`);
            if (tileActivo) tileActivo.classList.add("active-tile");

            // Mostrar y configurar panel
            const panel = document.getElementById("panelConfiguradorProducto");
            panel.classList.remove("d-none");

            document.getElementById("cfgProdCat").textContent = prod.categoria_nombre || "Bolsa de Hielo";
            document.getElementById("cfgProdNombre").textContent = prod.nombre;
            document.getElementById("cfgProdStock").textContent = `Stock disponible en cámara: ${prod.stock} bolsas`;
            document.getElementById("cfgProdPrecioLista").textContent = formatoMoneda.format(prod.precio_venta || prod.precio || 0);

            // Reiniciar inputs de cantidad y descuento para el nuevo producto
            document.getElementById("inputCantidadPOS").value = "1";
            document.getElementById("selectDescuentoPOS").value = "0";
            document.getElementById("inputDescuentoCustomPOS").classList.add("d-none");

            recalcularConfiguradorItem();
        }

        // 4. Recalcular precio del configurador en vivo
        function recalcularConfiguradorItem() {
            if (!productoSeleccionado) return;

            const inputCant = document.getElementById("inputCantidadPOS");
            const selectDesc = document.getElementById("selectDescuentoPOS");
            const customDesc = document.getElementById("inputDescuentoCustomPOS");

            let cant = Math.max(1, parseInt(inputCant.value) || 1);
            inputCant.value = cant;

            let descPorc = 0;
            if (selectDesc.value === "custom") {
                descPorc = Math.min(limiteDescuento, Math.max(0, parseFloat(customDesc.value) || 0));
            } else {
                descPorc = parseFloat(selectDesc.value) || 0;
            }

            const precioLista = Number(productoSeleccionado.precio_venta || productoSeleccionado.precio) || 0;
            const precioConDesc = descPorc > 0 ? (precioLista * (1 - descPorc / 100)) : precioLista;
            const subtotal = cant * precioConDesc;

            document.getElementById("cfgItemUnitarioFinal").textContent = formatoMoneda.format(precioConDesc);
            document.getElementById("cfgItemTotalFinal").textContent = formatoMoneda.format(subtotal);
        }

        // Steppers de Cantidad (+ / -)
        document.getElementById("btnRestarCantidad").addEventListener("click", () => {
            const inCant = document.getElementById("inputCantidadPOS");
            let c = Math.max(1, (parseInt(inCant.value) || 1) - 1);
            inCant.value = c;
            recalcularConfiguradorItem();
        });

        document.getElementById("btnSumarCantidad").addEventListener("click", () => {
            const inCant = document.getElementById("inputCantidadPOS");
            let c = (parseInt(inCant.value) || 1) + 1;
            inCant.value = c;
            recalcularConfiguradorItem();
        });

        document.getElementById("inputCantidadPOS").addEventListener("input", recalcularConfiguradorItem);

        // Botones Presets (+1, +5, +10, +20, +50)
        document.querySelectorAll(".btn-preset-cant").forEach(btn => {
            btn.addEventListener("click", () => {
                const add = parseInt(btn.dataset.cant) || 1;
                const inCant = document.getElementById("inputCantidadPOS");
                inCant.value = (parseInt(inCant.value) || 0) + add;
                recalcularConfiguradorItem();
            });
        });

        // Selector de Descuento
        document.getElementById("selectDescuentoPOS").addEventListener("change", (e) => {
            const customIn = document.getElementById("inputDescuentoCustomPOS");
            if (e.target.value === "custom") {
                customIn.classList.remove("d-none");
                customIn.focus();
            } else {
                customIn.classList.add("d-none");
            }
            recalcularConfiguradorItem();
        });
        document.getElementById("inputDescuentoCustomPOS").addEventListener("input", recalcularConfiguradorItem);

        // 5. Agregar Ítem al Ticket
        document.getElementById("btnAgregarAlTicket").addEventListener("click", () => {
            if (!productoSeleccionado) {
                alert("Por favor selecciona una bolsa de hielo.");
                return;
            }

            const stockNum = Number(productoSeleccionado.stock) || 0;
            const cant = Math.max(1, parseInt(document.getElementById("inputCantidadPOS").value) || 1);

            const yaEnTicket = ticketItems
                .filter(it => it.producto_id === productoSeleccionado.id)
                .reduce((acc, it) => acc + it.cantidad, 0);

            if ((yaEnTicket + cant) > stockNum) {
                alert(`Stock insuficiente para "${productoSeleccionado.nombre}". Disponible en cámara: ${stockNum} un. (Ya hay ${yaEnTicket} un. en el ticket).`);
                return;
            }

            const selectDesc = document.getElementById("selectDescuentoPOS");
            const customDesc = document.getElementById("inputDescuentoCustomPOS");
            let descPorc = selectDesc.value === "custom" ? (parseFloat(customDesc.value) || 0) : (parseFloat(selectDesc.value) || 0);

            const precioLista = Number(productoSeleccionado.precio_venta || productoSeleccionado.precio) || 0;
            const subtotalBruto = cant * precioLista;
            const descuentoMonto = subtotalBruto * (descPorc / 100);
            const totalItem = subtotalBruto - descuentoMonto;

            // Si ya existe el mismo producto con el mismo descuento, sumarlo
            const itemExistente = ticketItems.find(it => it.producto_id === productoSeleccionado.id && it.descuento_porcentaje === descPorc);

            if (itemExistente) {
                itemExistente.cantidad += cant;
                itemExistente.subtotal += subtotalBruto;
                itemExistente.descuento_monto += descuentoMonto;
                itemExistente.total += totalItem;
            } else {
                ticketItems.push({
                    producto_id: productoSeleccionado.id,
                    producto_nombre: productoSeleccionado.nombre,
                    tipo_venta: "unidad",
                    cantidad_empaque: cant,
                    cantidad: cant,
                    precio_unitario: precioLista,
                    descuento_porcentaje: descPorc,
                    descuento_monto: descuentoMonto,
                    subtotal: subtotalBruto,
                    total: totalItem
                });
            }

            // Animación feedback en botón
            const btn = document.getElementById("btnAgregarAlTicket");
            const originalHtml = btn.innerHTML;
            btn.className = "btn btn-success btn-lg fw-bold px-4 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2";
            btn.innerHTML = '<i class="bi bi-check2-circle fs-5"></i> <span>¡Agregado al Ticket!</span>';
            setTimeout(() => {
                btn.className = "btn btn-primary btn-lg fw-bold px-4 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2";
                btn.innerHTML = originalHtml;
            }, 600);

            renderizarTicket();
        });

        // 6. Renderizar Ticket / Carrito
        function renderizarTicket() {
            const contenedor = document.getElementById("cajaItemsTicket");
            const badgeCount = document.getElementById("ticketBadgeCount");
            const lblSubtotal = document.getElementById("ticketSubtotalBruto");
            const lblDesc = document.getElementById("ticketDescuentoMonto");
            const lblTotal = document.getElementById("ticketTotalPagar");

            if (ticketItems.length === 0) {
                contenedor.innerHTML = `
                    <div class="text-center text-muted py-4 bg-light rounded-3" id="ticketVacioMsg">
                        <i class="bi bi-cart-x fs-2 d-block text-muted mb-1"></i>
                        El ticket está vacío.<br>Toca un producto de hielo a la izquierda para cargarlo.
                    </div>
                `;
                badgeCount.textContent = "0 bolsas";
                lblSubtotal.textContent = "$ 0";
                lblDesc.textContent = "-$ 0";
                lblTotal.textContent = "$ 0";
                return;
            }

            contenedor.replaceChildren();

            let totalBolsas = 0;
            let subtotalGeneral = 0;
            let descuentoGeneral = 0;
            let totalPagarGeneral = 0;

            ticketItems.forEach((it, idx) => {
                totalBolsas += it.cantidad;
                subtotalGeneral += it.subtotal;
                descuentoGeneral += it.descuento_monto;
                totalPagarGeneral += it.total;

                const row = document.createElement("div");
                row.className = "ticket-item-row";

                const descBadge = it.descuento_porcentaje > 0 ? `<span class="badge text-bg-danger ms-1">-${it.descuento_porcentaje}%</span>` : "";

                row.innerHTML = `
                    <div style="max-width: 55%;">
                        <strong class="text-dark small d-block text-truncate">${escapeHtml(it.producto_nombre)}</strong>
                        <small class="text-muted">${formatoMoneda.format(it.precio_unitario)} c/u ${descBadge}</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-white border text-dark fw-bold px-2 py-1">${it.cantidad} un.</span>
                        <strong class="text-primary small">${formatoMoneda.format(it.total)}</strong>
                        <button type="button" class="btn btn-outline-danger btn-sm p-1 py-0" title="Eliminar ítem" data-del="${idx}">
                            <i class="bi bi-x fs-6"></i>
                        </button>
                    </div>
                `;

                row.querySelector("[data-del]").addEventListener("click", () => {
                    ticketItems.splice(idx, 1);
                    renderizarTicket();
                });

                contenedor.appendChild(row);
            });

            badgeCount.textContent = `${totalBolsas} bolsa${totalBolsas !== 1 ? 's' : ''}`;
            lblSubtotal.textContent = formatoMoneda.format(subtotalGeneral);
            lblDesc.textContent = `-${formatoMoneda.format(descuentoGeneral)}`;
            lblTotal.textContent = formatoMoneda.format(totalPagarGeneral);
        }

        // Vaciar Ticket
        document.getElementById("btnVaciarTicket").addEventListener("click", () => {
            if (ticketItems.length > 0 && confirm("¿Deseas vaciar todo el ticket de venta?")) {
                ticketItems = [];
                renderizarTicket();
            }
        });

        // 7. Toggle y Datos de Cliente
        document.getElementById("btnToggleDatosCliente").addEventListener("click", () => {
            const caja = document.getElementById("cajaDatosCliente");
            caja.classList.toggle("d-none");
        });

        document.getElementById("posClienteNombre").addEventListener("input", (e) => {
            document.getElementById("lblClienteActivo").textContent = e.target.value.trim() || "Consumidor Final";
        });

        // 8. Confirmar y Cobrar Venta (Unificada)
        document.getElementById("btnConfirmarCobroVenta").addEventListener("click", async () => {
            const alertErr = document.getElementById("alertaError");
            const alertOk = document.getElementById("alertaExito");
            alertErr.classList.add("d-none");
            alertOk.classList.add("d-none");

            if (ticketItems.length === 0) {
                alertErr.textContent = "El ticket está vacío. Selecciona al menos una bolsa de hielo antes de cobrar.";
                alertErr.classList.remove("d-none");
                return;
            }

            const btnCobrar = document.getElementById("btnConfirmarCobroVenta");
            const originalText = btnCobrar.innerHTML;
            btnCobrar.disabled = true;
            btnCobrar.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Procesando venta...`;

            try {
                const clienteNombre = document.getElementById("posClienteNombre").value.trim() || "Consumidor Final";
                const clienteTelefono = document.getElementById("posClienteTelefono").value.trim();
                const clienteDireccion = document.getElementById("posClienteDireccion").value.trim();

                const payload = {
                    cliente_nombre: clienteNombre,
                    cliente_telefono: clienteTelefono,
                    cliente_direccion: clienteDireccion,
                    items: ticketItems
                };

                const resp = await fetch("guardar_venta.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-Token": csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                const data = await resp.json().catch(() => ({}));
                if (!resp.ok) throw new Error(data.error || "No se pudo registrar la venta.");

                alertOk.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>¡Venta registrada con éxito!</strong><br>
                            Ticket: <strong>${data.ticket_id || 'TK-000'}</strong> • Total: <strong>${formatoMoneda.format(data.total || 0)}</strong>
                        </div>
                        <a href="generar_remito.php?id=${data.id || data.ventas_ids[0]}" target="_blank" class="btn btn-dark btn-sm text-white fw-bold">
                            <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> Ver Remito
                        </a>
                    </div>
                `;
                alertOk.classList.remove("d-none");

                // Limpiar Ticket y recargar stock
                ticketItems = [];
                renderizarTicket();
                await cargarProductosPOS();

                window.scrollTo({ top: 0, behavior: "smooth" });

            } catch (err) {
                alertErr.textContent = err.message;
                alertErr.classList.remove("d-none");
                window.scrollTo({ top: 0, behavior: "smooth" });
            } finally {
                btnCobrar.disabled = false;
                btnCobrar.innerHTML = originalText;
            }
        });

        // Helper para escape HTML
        function escapeHtml(texto) {
            const div = document.createElement("div");
            div.textContent = texto || "";
            return div.innerHTML;
        }

        // Inicializar al cargar
        document.addEventListener("DOMContentLoaded", cargarProductosPOS);
    </script>
</body>
</html>
