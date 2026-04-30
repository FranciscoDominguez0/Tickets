<?php
/**
 * Módulo: Mapa de Agentes en Tiempo Real
 * Muestra la ubicación de los agentes que tienen tickets "En camino" usando Leaflet.js
 */

// Asegurar que el agente esté logueado (aunque ya lo hace el router)
if (!isset($_SESSION['staff_id'])) exit;
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">

<style>
    /* Estilos del Hero Principal (Adaptado de stats-hero) */
    .map-hero {
        background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 55%, #0ea5e9 100%);
        border: 1px solid rgba(37, 99, 235, 0.2);
        border-radius: 14px;
        padding: 1.5rem 2rem;
        color: #fff;
        box-shadow: 0 14px 32px rgba(37, 99, 235, 0.28);
        margin-bottom: 16px;
    }
    .map-hero-title {
        font-size: 1.45rem;
        font-weight: 700;
        margin: 0;
        color: #fff;
    }
    .map-hero-sub {
        margin: .2rem 0 0;
        color: rgba(255, 255, 255, .9);
        font-size: .95rem;
        font-weight: 600;
    }
    .map-hero-icon {
        width: 52px;
        height: 52px;
        background: rgba(255, 255, 255, .18);
        color: #fff;
        border-radius: 14px;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        box-shadow: 0 4px 14px rgba(2, 6, 23, .2);
        border: 1px solid rgba(255, 255, 255, .22);
    }
    .btn-refresh-map {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        padding: 8px 16px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    .btn-refresh-map:hover {
        background: rgba(255, 255, 255, 0.25);
        color: white;
    }

    /* Layout del Mapa */
    .map-wrapper {
        display: flex;
        gap: 24px;
        height: calc(100vh - 260px);
        min-height: 600px;
    }

    .map-sidebar {
        width: 320px;
        flex-shrink: 0;
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 10px 30px rgba(0,0,0,0.04);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .sidebar-header {
        padding: 20px 24px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar-header h4 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 800;
        color: #0f172a;
    }

    .agent-badge {
        background: #eff6ff;
        color: #2563eb;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .agent-list {
        flex: 1;
        overflow-y: auto;
        padding: 12px;
    }

    .agent-item {
        padding: 16px;
        border-radius: 18px;
        margin-bottom: 10px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        border: 1px solid transparent;
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .agent-item:hover {
        background: #f8fafc;
        border-color: #e2e8f0;
        transform: scale(1.02);
    }

    .agent-item.active {
        background: #f0f7ff;
        border-color: #bfdbfe;
    }

    .agent-avatar-container {
        position: relative;
    }

    .agent-avatar {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.2rem;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }

    .status-indicator {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 14px;
        height: 14px;
        background: #10b981;
        border: 3px solid white;
        border-radius: 50%;
    }

    .agent-info {
        flex: 1;
        min-width: 0;
    }

    .agent-name {
        font-weight: 700;
        font-size: 0.95rem;
        color: #1e293b;
        margin-bottom: 2px;
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .agent-sub {
        font-size: 0.8rem;
        color: #64748b;
        font-weight: 500;
    }

    .map-container-outer {
        flex: 1;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        border: 1px solid rgba(226, 232, 240, 0.8);
        position: relative;
        background: #f8fafc;
        min-height: 400px;
    }

    #map {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        width: 100%;
        height: 100%;
        z-index: 1;
    }

    /* Tabs para móvil - ELIMINADOS: layout vertical directo */

    /* Custom Leaflet Tooltip & Popup */
    .leaflet-popup-content-wrapper {
        border-radius: 16px;
        padding: 5px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .agent-popup {
        padding: 10px;
        min-width: 180px;
    }
    .agent-popup h5 {
        margin: 0 0 8px;
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
    }
    .popup-stat {
        display: flex;
        justify-content: space-between;
        margin-bottom: 4px;
        font-size: 0.85rem;
    }
    .popup-label { color: #64748b; font-weight: 500; }
    .popup-value { color: #1e293b; font-weight: 700; }

    /* Marcador Personalizado */
    .custom-marker { position: relative; }
    .marker-pulse {
        width: 32px; height: 32px;
        background: rgba(37, 99, 235, 0.2);
        border-radius: 50%;
        position: absolute;
        top: 50%; left: 50%;
        margin: -16px 0 0 -16px;
        animation: pulse 2s infinite;
    }
    .marker-core {
        width: 22px; height: 22px;
        background: #2563eb;
        border: 3px solid white;
        border-radius: 50%;
        position: absolute;
        top: 50%; left: 50%;
        margin: -11px 0 0 -11px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        z-index: 2;
    }
    .marker-label-pro {
        position: absolute;
        top: -35px; left: 50%;
        transform: translateX(-50%);
        background: #2563eb;
        color: white;
        padding: 4px 12px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 800;
        white-space: nowrap;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        border: 1px solid white;
    }
    .marker-label-pro::after {
        content: '';
        position: absolute;
        bottom: -4px; left: 50%;
        margin-left: -4px;
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        border-top: 4px solid #0f172a;
    }

    @keyframes pulse {
        0% { transform: scale(1); opacity: 1; }
        100% { transform: scale(2.5); opacity: 0; }
    }
    .spin { animation: fa-spin 1s infinite linear; }
    @keyframes fa-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

    /* Responsividad Tablet */
    @media (max-width: 1100px) {
        .map-wrapper { gap: 16px; }
        .map-sidebar { width: 280px; }
    }

    /* Responsividad Móvil: todo en una columna, sin tabs */
    @media (max-width: 768px) {
        .map-wrapper {
            flex-direction: column;
            height: auto;
            min-height: 0;
            gap: 12px;
        }
        .map-sidebar {
            width: 100%;
            height: auto;
            max-height: 220px;
            border-radius: 16px;
            flex-shrink: 0;
        }
        .map-container-outer {
            height: 55vh !important;
            min-height: 320px;
            border-radius: 16px;
        }
        .map-hero { padding: 20px 16px; border-radius: 16px; }
        .map-hero-title { font-size: 1.2rem; }
        .map-hero-sub { font-size: 0.85rem; }
    }
</style>

<div class="map-hero d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
    <div class="d-flex align-items-center gap-3">
        <span class="map-hero-icon"><i class="bi bi-geo-alt-fill"></i></span>
        <div>
            <h3 class="map-hero-title">Rastreo de Agentes</h3>
            <div class="map-hero-sub">Seguimiento estratégico de agentes en terreno en tiempo real.</div>
        </div>
    </div>
    <div class="map-hero-actions d-flex align-items-center">
        <button id="refresh-map" class="btn btn-refresh-map">
            <i class="bi bi-arrow-clockwise"></i> <span>Sincronizar</span>
        </button>
    </div>
</div>

<div class="map-wrapper">
    <aside class="map-sidebar" id="mapSidebar">
        <div class="sidebar-header">
            <h4>Agentes Activos</h4>
            <span class="agent-badge" id="active-agents-count">0</span>
        </div>
        <div id="agent-list" class="agent-list">
            <div class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                <div class="small fw-bold">Buscando técnicos...</div>
            </div>
        </div>
    </aside>

    <div class="map-container-outer" id="mapContainerOuter">
        <div id="map"></div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar mapa
    var map = L.map('map', {
        zoomControl: false // Quitamos los controles por defecto para ponerlos más estéticos
    }).setView([0, 0], 2);

    // Agregar control de zoom en una posición más limpia
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    // Tiles estándar de OpenStreetMap para mejor visibilidad de calles y terreno
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);

    var markers = {};
    var agentGroup = L.featureGroup().addTo(map);

    // Alerta de no agentes
    var noAgentsMsg = L.control({position: 'topright'});
    noAgentsMsg.onAdd = function(map) {
        var div = L.DomUtil.create('div', 'alert alert-info py-2 px-3 m-3 border-0 shadow-sm d-none');
        div.id = 'no-agents-alert';
        div.style.borderRadius = '12px';
        div.style.background = 'rgba(15, 23, 42, 0.9)';
        div.style.color = 'white';
        div.style.backdropFilter = 'blur(10px)';
        div.innerHTML = '<i class="bi bi-info-circle-fill me-2 text-info"></i> No hay técnicos en camino';
        return div;
    };
    noAgentsMsg.addTo(map);

    function fetchLocations() {
        fetch('ajax_location.php?action=get_locations')
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    updateMarkers(data.locations);
                }
            })
            .catch(e => console.error('Error fetching locations:', e));
    }

    function updateMarkers(locations) {
        document.getElementById('active-agents-count').textContent = locations.length;
        
        var alertEl = document.getElementById('no-agents-alert');
        var sidebarList = document.getElementById('agent-list');
        
        if (locations.length === 0) {
            if (alertEl) alertEl.classList.remove('d-none');
            sidebarList.innerHTML = `
                <div class="text-center py-5">
                    <div class="mb-3"><i class="bi bi-geo-alt text-muted" style="font-size: 2rem; opacity: 0.3;"></i></div>
                    <div class="text-muted small fw-bold">Sin actividad reportada</div>
                </div>`;
        } else {
            if (alertEl) alertEl.classList.add('d-none');
            sidebarList.innerHTML = '';
        }
        
        var currentIds = locations.map(l => l.staff_id);
        
        // Limpiar marcadores antiguos
        Object.keys(markers).forEach(id => {
            if (!currentIds.includes(parseInt(id))) {
                agentGroup.removeLayer(markers[id]);
                delete markers[id];
            }
        });

        locations.forEach(loc => {
            var popupContent = `
                <div class="agent-popup">
                    <h5>${loc.name}</h5>
                    <div class="popup-stat">
                        <span class="popup-label">Ticket:</span>
                        <span class="popup-value">#${loc.ticket_number}</span>
                    </div>
                    <div class="popup-stat">
                        <span class="popup-label">Estado:</span>
                        <span class="badge" style="background:#dcfce7; color:#166534; border-radius:6px; font-weight:700;">${loc.status}</span>
                    </div>
                    <hr style="margin: 10px 0; border-top: 1px solid #f1f5f9;">
                    <a href="tickets.php?id=${loc.ticket_id}" class="btn btn-primary btn-sm w-100 text-white fw-bold py-2" style="border-radius: 10px; font-size: 0.8rem;">
                        <i class="bi bi-eye-fill"></i> Ver Detalles
                    </a>
                </div>
            `;

            if (markers[loc.staff_id]) {
                markers[loc.staff_id].setLatLng([loc.lat, loc.lng]);
                markers[loc.staff_id].getPopup().setContent(popupContent);
            } else {
                var customIcon = L.divIcon({
                    className: 'custom-marker',
                    html: `
                        <div class="marker-pulse" style="background: rgba(37, 99, 235, 0.4);"></div>
                        <div class="marker-core" style="background: #2563eb; width: 22px; height: 22px; margin: -11px 0 0 -11px;"></div>
                        <div class="marker-label-pro" style="background: #2563eb; border: 1px solid white; top: -35px;">${loc.name.split(' ')[0]}</div>
                    `,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                });

                var marker = L.marker([loc.lat, loc.lng], {icon: customIcon}).bindPopup(popupContent);
                markers[loc.staff_id] = marker;
                agentGroup.addLayer(marker);
            }

            // AGREGAR AL SIDEBAR
            var item = document.createElement('div');
            item.className = 'agent-item';
            item.onclick = () => {
                var isMobile = window.innerWidth <= 768;

                if (isMobile) {
                    // En móvil: primero desplazar al mapa sin animación y luego centrar
                    map.setView([loc.lat, loc.lng], 16);
                    markers[loc.staff_id].openPopup();
                    // Scroll suave al contenedor del mapa
                    var mapEl = document.getElementById('mapContainerOuter');
                    if (mapEl) {
                        mapEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                } else {
                    map.flyTo([loc.lat, loc.lng], 17, { duration: 1.5 });
                    markers[loc.staff_id].openPopup();
                }

                // Marcar como activo en el sidebar
                document.querySelectorAll('.agent-item').forEach(el => el.classList.remove('active'));
                item.classList.add('active');
            };
            
            var initial = loc.name.charAt(0).toUpperCase();
            
            item.innerHTML = `
                <div class="agent-avatar-container">
                    <div class="agent-avatar">${initial}</div>
                    <div class="status-indicator"></div>
                </div>
                <div class="agent-info">
                    <span class="agent-name">${loc.name}</span>
                    <span class="agent-sub">Ticket #${loc.ticket_number} &bull; ${loc.status}</span>
                </div>
            `;
            sidebarList.appendChild(item);
        });

        if (locations.length > 0 && agentGroup.getBounds().isValid()) {
            if (map.getZoom() < 3) {
                map.fitBounds(agentGroup.getBounds(), {padding: [50, 50]});
            }
        }
    }

    // Polling cada 8 segundos
    fetchLocations();
    setInterval(fetchLocations, 8000);

    // Botón Actualizar
    document.getElementById('refresh-map').addEventListener('click', function() {
        var btn = this;
        var icon = btn.querySelector('i');
        var text = btn.querySelector('span');
        
        btn.disabled = true;
        icon.classList.add('spin');
        text.textContent = 'Actualizando...';
        
        fetchLocations();
        
        setTimeout(() => {
            btn.disabled = false;
            icon.classList.remove('spin');
            text.textContent = 'Sincronizar';
        }, 1200);
    });

    // Ajustar tamaño del mapa tras carga inicial
    setTimeout(() => map.invalidateSize(), 100);
    setTimeout(() => map.invalidateSize(), 400);
    setTimeout(() => map.invalidateSize(), 900);
});
</script>
