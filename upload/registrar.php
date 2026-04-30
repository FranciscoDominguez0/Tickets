<?php
/**
 * REGISTRO DE CLIENTE
 * Formulario para que nuevos clientes se registren
 */

require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

if ((string)getAppSetting('system.helpdesk_status', 'online') === 'offline') {
    header('Location: login.php?msg=offline');
    exit;
}

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: tickets.php');
    exit;
}

$error = '';
$success = '';

if (!function_exists('normalizePhoneDigits')) {
    function normalizePhoneDigits($value) {
        return preg_replace('/\D+/', '', (string)$value);
    }
}

if ($_POST) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad inválido';
    } else {
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $phone_raw = trim((string)($_POST['phone'] ?? ''));
        $address = trim($_POST['address'] ?? '');
        $lat = isset($_POST['latitude']) && is_numeric($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $lng = isset($_POST['longitude']) && is_numeric($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $phone_digits = normalizePhoneDigits($phone_raw);

        // Validaciones
        if (!$firstname || !$lastname || !$email || !$password || $phone_raw === '' || !$address) {
            $error = 'Nombre, apellido, email, dirección, teléfono y contraseña son requeridos';
        } elseif (!isValidEmail($email)) {
            $error = 'Email no válido';
        } elseif (!preg_match('/^\d{7,15}$/', $phone_digits)) {
            $error = 'El teléfono debe contener solo números y tener entre 7 y 15 dígitos.';
        } elseif (strlen($password) < 6) {
            $error = 'Contraseña debe tener al menos 6 caracteres';
        } elseif ($password !== $password_confirm) {
            $error = 'Las contraseñas no coinciden';
        } else {
            // Verificar si email existe
            $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = 'Este email ya está registrado';
            } else {
                // Hash de contraseña
                $password_hash = password_hash($password, PASSWORD_BCRYPT);

                // Insertar usuario
                $stmt = $mysqli->prepare(
                    'INSERT INTO users (firstname, lastname, email, address, latitude, longitude, password, company, phone, status, created)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "active", NOW())'
                );
                $company = '';
                $stmt->bind_param('ssssddsss', $firstname, $lastname, $email, $address, $lat, $lng, $password_hash, $company, $phone_digits);

                if ($stmt->execute()) {
                    $success = 'Registro exitoso! Redirigiendo al login...';
                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "login.php";
                        }, 2000);
                    </script>';
                } else {
                    $error = 'Error al registrarse: ' . $mysqli->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../publico/css/login.css?v=<?php echo (int)(@filemtime(__DIR__ . '/../publico/css/login.css') ?: time()); ?>">
    <link rel="icon" type="image/x-icon" href="../publico/img/favicon.ico">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .login-panel { padding: 32px !important; }
        .login-form { gap: 18px !important; }
        .form-grid { gap: 18px !important; grid-template-columns: 1fr 1fr !important; align-items: start !important; }
        .form-group label { font-size: 14px !important; }
        .form-group input { padding: 13px 14px !important; font-size: 15px !important; }
        .btn-login { padding: 13px 16px !important; font-size: 15px !important; }
        
        .map-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 8px; padding: 13px 14px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; margin-top: 0; text-decoration: none; width: 100%; box-sizing: border-box; }
        .map-btn:hover { background: #e2e8f0; color: #0f172a; border-color: #94a3b8; }
        .map-btn i { color: #2563eb; }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        .shake-anim { animation: shake 0.4s ease-in-out; }

        @media (max-width: 760px) {
            .login-panel { padding: 22px !important; }
            .form-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<?php
$bgMode = (string)getAppSetting('login.background_mode', 'default');
$loginBg = $bgMode === 'custom' ? (string)getBrandAssetUrl('login.background', '') : '';
$bodyStyle = $loginBg !== ''
    ? ('background-image: linear-gradient(135deg, rgba(240, 244, 248, 0.92) 0%, rgba(226, 232, 240, 0.92) 100%), url(' . html($loginBg) . '); background-size: cover, cover; background-position: center, center; background-repeat: no-repeat, no-repeat;')
    : '';
?>
<body style="<?php echo $bodyStyle; ?>">
    <div class="support-center-wrapper">
        <!-- HEADER SUPERIOR -->
        <div class="support-header">
            <div class="support-header-left">
                <?php $brandLogo = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png'); ?>
                <img src="<?php echo html($brandLogo); ?>" alt="VIGITEC PANAMA" class="vigitec-logo">
            </div>
            <div class="support-header-right">
                <span class="guest-user">Usuario Invitado</span>
                <span class="header-separator">|</span>
                <a href="login.php" class="header-login-link">Inicia Sesión</a>
            </div>
        </div>

        <!-- NAVEGACIÓN -->
        <div class="support-nav">
            <button class="nav-item active">Inicio Centro de Soporte</button>
        </div>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="support-content" style="max-width: 1320px;">
            <div class="welcome-section">
                <h2 class="welcome-title">Cree una cuenta en <?php echo APP_NAME; ?></h2>
                <p class="welcome-text">Complete el formulario para registrarse. Si ya tiene cuenta, puede iniciar sesión.</p>
            </div>

            <!-- PANEL DE REGISTRO -->
            <div class="login-panel" style="grid-template-columns: 1fr; width: 100%; max-width: 1240px; margin-left: auto; margin-right: auto;">
                <div class="login-panel-left" style="width: 100%;">
                    <form method="post" class="login-form">
                        <!-- Alertas -->
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="firstname">Nombre</label>
                                <input 
                                    type="text" 
                                    id="firstname" 
                                    name="firstname" 
                                    placeholder="Tu nombre"
                                    value="<?php echo html($_POST['firstname'] ?? ''); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="lastname">Apellido</label>
                                <input 
                                    type="text" 
                                    id="lastname" 
                                    name="lastname" 
                                    placeholder="Tu apellido"
                                    value="<?php echo html($_POST['lastname'] ?? ''); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="email">Correo electrónico</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    placeholder="tu@email.com"
                                    value="<?php echo html($_POST['email'] ?? ''); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="address">Dirección</label>
                                <input type="hidden" name="address" id="address" value="<?php echo html($_POST['address'] ?? ''); ?>">
                                <input type="hidden" name="latitude" id="latitude" value="<?php echo html($_POST['latitude'] ?? ''); ?>">
                                <input type="hidden" name="longitude" id="longitude" value="<?php echo html($_POST['longitude'] ?? ''); ?>">
                                
                                <button type="button" class="map-btn" id="btnOpenMap" style="margin-top: 0;">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M8 0C5.2 0 3 2.2 3 5c0 3.5 5 11 5 11s5-7.5 5-11c0-2.8-2.2-5-5-5zm0 7.5c-1.4 0-2.5-1.1-2.5-2.5S6.6 2.5 8 2.5 10.5 3.6 10.5 5 9.4 7.5 8 7.5z"/></svg>
                                    Fijar ubicación exacta en el mapa
                                </button>
                                
                                <div id="mapStatusText" style="font-size: 0.85rem; color: #166534; font-weight: 600; margin-top: 8px; display: <?php echo !empty($_POST['address']) ? 'flex' : 'none'; ?>; align-items: center; gap: 6px;">
                                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Ubicación lista para registrarse.
                                </div>
                                <div id="mapErrorText" style="display:none; color:#dc2626; font-size:13px; margin-top:6px; font-weight:600;">
                                    Este campo es obligatorio.
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="phone">Teléfono</label>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    placeholder="Solo números"
                                    value="<?php echo html($_POST['phone'] ?? ''); ?>"
                                    inputmode="numeric"
                                    pattern="\d{7,15}"
                                    minlength="7"
                                    maxlength="15"
                                    required
                                >
                                <small>Entre 7 y 15 dígitos numéricos.</small>
                            </div>

                            <div class="form-group">
                                <label for="password">Contraseña</label>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    placeholder="Contraseña"
                                    required
                                >
                                <small>Mínimo 6 caracteres</small>
                            </div>

                            <div class="form-group">
                                <label for="password_confirm">Confirmar Contraseña</label>
                                <input 
                                    type="password" 
                                    id="password_confirm" 
                                    name="password_confirm" 
                                    placeholder="Confirmar contraseña"
                                    required
                                >
                            </div>
                        </div>

                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <button type="submit" class="btn-login">Crear cuenta</button>
                    </form>
                </div>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="support-footer">
            <p class="copyright">
                Derechos de autor &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getAppSetting('company.name', 'Vigitec Panama')); ?> - <?php echo APP_NAME; ?> - Todos los derechos reservados.
            </p>
        </div>
    </div>

    <!-- MAP MODAL -->
    <div id="mapModalOverlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.8); z-index:9999; align-items:center; justify-content:center; padding:16px;">
        <div style="background:#fff; border-radius:16px; width:100%; max-width:650px; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); display:flex; flex-direction:column; max-height: 95vh;">
            <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#fff;">
                <h3 style="margin:0; font-size:18px; font-weight:700; color:#0f172a;">📍 Fija tu ubicación</h3>
                <button type="button" id="btnCloseMap" style="background:#f1f5f9; border:none; border-radius:50%; width:32px; height:32px; font-size:20px; line-height:1; color:#64748b; cursor:pointer; display:flex; align-items:center; justify-content:center; transition: background 0.2s;">&times;</button>
            </div>
            
            <div style="position: relative; height: 55vh; min-height: 350px; width:100%; background:#e2e8f0;">
                <div style="position: absolute; top: 12px; left: 50px; right: 12px; z-index: 1000; display: flex; gap: 8px; background: white; padding: 6px 8px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                    <input type="text" id="mapSearchInput" placeholder="Buscar calle, ciudad o lugar..." style="flex:1; border:none; outline:none; font-size:14px; padding: 4px; background: transparent; min-width: 0;">
                    <button type="button" id="mapSearchBtn" style="background:#2563eb; color:white; border:none; border-radius:6px; padding:6px 14px; font-size:13px; font-weight:600; cursor:pointer; transition: background 0.2s; white-space: nowrap;">Buscar</button>
                </div>
                
                <div id="mapContainer" style="height: 100%; width: 100%;"></div>
            </div>

            <div style="padding:12px 20px; background:#f8fafc; border-top:1px solid #e2e8f0; font-size:13px; color:#475569; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div style="flex: 1; min-width: 200px;">
                    Mueve el marcador o haz clic en el mapa.
                </div>
                <button type="button" id="btnLocateMe" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; border-radius:8px; padding:8px 14px; font-size:13px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:6px; transition: all 0.2s;">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 0a8 8 0 100 16A8 8 0 008 0zm0 14A6 6 0 118 2a6 6 0 010 12z"/><circle cx="8" cy="8" r="3"/></svg>
                    Usar mi GPS
                </button>
            </div>
            
            <div style="padding:16px 20px; background:#fff; display:flex; justify-content:flex-end; gap:12px; border-top: 1px solid #f1f5f9;">
                <button type="button" id="btnCancelMap" style="padding:10px 18px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; color:#475569; font-weight:600; font-size:14px; cursor:pointer; transition: background 0.2s;">Cancelar</button>
                <button type="button" id="btnConfirmMap" style="padding:10px 18px; border-radius:8px; border:none; background:#2563eb; color:#fff; font-weight:600; font-size:14px; cursor:pointer; transition: background 0.2s; box-shadow: 0 2px 4px rgba(37,99,235,0.3);">Guardar ubicación</button>
            </div>
    </div>

    <script>
        // Lógica del Formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            var latInput = document.getElementById('latitude').value;
            var lngInput = document.getElementById('longitude').value;
            var addressInput = document.getElementById('address').value;

            if (!latInput || !lngInput || !addressInput) {
                e.preventDefault();
                var btnMap = document.getElementById('btnOpenMap');
                var errTxt = document.getElementById('mapErrorText');
                
                // Estilo rojo de error al botón
                btnMap.style.borderColor = '#ef4444';
                btnMap.style.backgroundColor = '#fef2f2';
                btnMap.style.color = '#b91c1c';
                errTxt.style.display = 'block';
                
                // Sacudida
                btnMap.classList.remove('shake-anim');
                void btnMap.offsetWidth; // Trigger reflow
                btnMap.classList.add('shake-anim');
                
                return false;
            }

            var phoneInput = this.querySelector('#phone');
            var phoneDigits = (phoneInput && phoneInput.value ? phoneInput.value : '').replace(/\D+/g, '');
            if (!/^\d{7,15}$/.test(phoneDigits)) {
                e.preventDefault();
                alert('El teléfono es obligatorio y debe tener entre 7 y 15 números.');
                if (phoneInput) phoneInput.focus();
                return false;
            }
            if (phoneInput) {
                phoneInput.value = phoneDigits;
            }
            const btn = this.querySelector('.btn-login');
            if (btn.disabled) {
                e.preventDefault();
                return false;
            }
            btn.disabled = true;
            btn.classList.add('loading');
            btn.textContent = 'Registrando...';
        });

        // Lógica del Mapa Leaflet
        (function() {
            var btnOpenMap = document.getElementById('btnOpenMap');
            var btnCloseMap = document.getElementById('btnCloseMap');
            var btnCancelMap = document.getElementById('btnCancelMap');
            var btnConfirmMap = document.getElementById('btnConfirmMap');
            var btnLocateMe = document.getElementById('btnLocateMe');
            var modalOverlay = document.getElementById('mapModalOverlay');
            var mapContainer = document.getElementById('mapContainer');
            
            var latInput = document.getElementById('latitude');
            var lngInput = document.getElementById('longitude');
            var addressInput = document.getElementById('address');
            var statusText = document.getElementById('mapStatusText');

            var map = null;
            var marker = null;
            var currentLat = 8.537981; // Centro aproximado de Panamá (por defecto)
            var currentLng = -80.782127;

            function initMap() {
                if (map !== null) return;
                map = L.map('mapContainer').setView([currentLat, currentLng], 8);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);

                marker = L.marker([currentLat, currentLng], {draggable: true}).addTo(map);

                marker.on('dragend', function(e) {
                    var pos = marker.getLatLng();
                    currentLat = pos.lat;
                    currentLng = pos.lng;
                    updateAddressFromCoords(currentLat, currentLng);
                });

                map.on('click', function(e) {
                    currentLat = e.latlng.lat;
                    currentLng = e.latlng.lng;
                    marker.setLatLng(e.latlng);
                    updateAddressFromCoords(currentLat, currentLng);
                });
            }

            function updateAddressFromCoords(lat, lng) {
                fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&zoom=18&addressdetails=1')
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.display_name) {
                            addressInput.value = data.display_name;
                        }
                    })
                    .catch(err => console.error('Geocoding error', err));
            }

            // Lógica del buscador Nominatim
            var mapSearchInput = document.getElementById('mapSearchInput');
            var mapSearchBtn = document.getElementById('mapSearchBtn');

            function performSearch() {
                var query = mapSearchInput.value.trim();
                if (!query) return;
                mapSearchBtn.textContent = '...';
                
                // Añadir "Panamá" a la búsqueda para ayudar al geocoder si no lo tiene
                var searchQuery = query;
                if (searchQuery.toLowerCase().indexOf('panama') === -1 && searchQuery.toLowerCase().indexOf('panamá') === -1) {
                    searchQuery += ', Panamá';
                }

                // Usamos Photon (basado en OSM) que es más permisivo con CORS y no bloquea sin User-Agent
                var url = 'https://photon.komoot.io/api/?q=' + encodeURIComponent(searchQuery) + '&limit=1';
                
                fetch(url)
                    .then(r => {
                        if (!r.ok) throw new Error('Error HTTP: ' + r.status);
                        return r.json();
                    })
                    .then(data => {
                        if (data && data.features && data.features.length > 0) {
                            // Photon devuelve coordinates como [lon, lat]
                            var coords = data.features[0].geometry.coordinates;
                            currentLat = parseFloat(coords[1]);
                            currentLng = parseFloat(coords[0]);
                            map.setView([currentLat, currentLng], 15);
                            marker.setLatLng([currentLat, currentLng]);
                            updateAddressFromCoords(currentLat, currentLng);
                        } else {
                            // Intento de respaldo sin "Panamá"
                            fetch('https://photon.komoot.io/api/?q=' + encodeURIComponent(query) + '&limit=1')
                                .then(r2 => r2.json())
                                .then(data2 => {
                                    if (data2 && data2.features && data2.features.length > 0) {
                                        var coords2 = data2.features[0].geometry.coordinates;
                                        currentLat = parseFloat(coords2[1]);
                                        currentLng = parseFloat(coords2[0]);
                                        map.setView([currentLat, currentLng], 15);
                                        marker.setLatLng([currentLat, currentLng]);
                                        updateAddressFromCoords(currentLat, currentLng);
                                    } else {
                                        alert('No se encontraron resultados para: "' + query + '".\nIntenta buscar la ciudad y mueve el pin manualmente.');
                                    }
                                })
                                .catch(err => alert('Hubo un problema con el buscador. Intenta mover el mapa manualmente.'));
                        }
                    })
                    .catch(err => {
                        console.error('Search error', err);
                        alert('Error de conexión con el buscador. Mueve el mapa manualmente a tu ubicación.');
                    })
                    .finally(() => {
                        mapSearchBtn.textContent = 'Buscar';
                    });
            }

            mapSearchBtn.addEventListener('click', performSearch);
            mapSearchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performSearch();
                }
            });

            function openMap() {
                modalOverlay.style.display = 'flex';
                // Checar si ya hay valor
                if (latInput.value && lngInput.value && !isNaN(latInput.value)) {
                    currentLat = parseFloat(latInput.value);
                    currentLng = parseFloat(lngInput.value);
                }
                
                if (!map) {
                    initMap();
                }
                
                setTimeout(function() {
                    map.invalidateSize();
                    map.setView([currentLat, currentLng], 15);
                    marker.setLatLng([currentLat, currentLng]);
                }, 200);
            }

            function closeMap() {
                modalOverlay.style.display = 'none';
            }

            btnOpenMap.addEventListener('click', openMap);
            btnCloseMap.addEventListener('click', closeMap);
            btnCancelMap.addEventListener('click', closeMap);

            btnConfirmMap.addEventListener('click', function() {
                latInput.value = currentLat.toFixed(8);
                lngInput.value = currentLng.toFixed(8);
                
                // Ocultar error si existía y mostrar check verde
                var errTxt = document.getElementById('mapErrorText');
                if(errTxt) errTxt.style.display = 'none';
                statusText.style.display = 'flex';
                
                btnOpenMap.style.color = '#166534';
                btnOpenMap.style.background = '#f0fdf4';
                btnOpenMap.style.borderColor = '#bbf7d0';
                closeMap();
            });

            btnLocateMe.addEventListener('click', function() {
                if (navigator.geolocation) {
                    btnLocateMe.textContent = "Buscando...";
                    navigator.geolocation.getCurrentPosition(function(pos) {
                        currentLat = pos.coords.latitude;
                        currentLng = pos.coords.longitude;
                        map.setView([currentLat, currentLng], 16);
                        marker.setLatLng([currentLat, currentLng]);
                        updateAddressFromCoords(currentLat, currentLng);
                        btnLocateMe.textContent = "📍 Usar mi GPS";
                    }, function(err) {
                        alert("No se pudo obtener la ubicación. Por favor, asegúrate de haber dado permiso al navegador.");
                        btnLocateMe.textContent = "📍 Usar mi GPS";
                    }, { enableHighAccuracy: true });
                } else {
                    alert("Tu navegador no soporta geolocalización.");
                }
            });
            
            // Mostrar texto verde si ya hay coords (al recargar con error form)
            if (latInput.value && lngInput.value) {
                statusText.style.display = 'block';
                btnOpenMap.style.background = '#f0fdf4';
                btnOpenMap.style.borderColor = '#bbf7d0';
            }
        })();
    </script>
</body>
</html>
