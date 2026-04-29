<?php
/**
 * Módulo: Mapa de Agentes en Tiempo Real
 * Muestra la ubicación de los agentes que tienen tickets "En camino" usando Leaflet.js
 */

// Asegurar que el agente esté logueado (aunque ya lo hace el router)
if (!isset($_SESSION['staff_id'])) exit;
?>

<!-- Leaflet CSS (Usando jsDelivr porque unpkg está bloqueado por CSP) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">

<style>
    .map-wrapper {
        position: relative;
        height: calc(100vh - 180px);
        min-height: 600px;
        display: flex;
        gap: 20px;
    }
    .map-container {
        flex: 1;
        height: 100%;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        border: 1px solid rgba(226, 232, 240, 0.8);
        position: relative;
    }
    #map {
        height: 100%;
        width: 100%;
        z-index: 1;
    }
    .agent-sidebar {
        width: 320px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .sidebar-header {
        padding: 20px;
        border-bottom: 1px solid #f1f5f9;
        background: #f8fafc;
    }
    .sidebar-header h4 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 800;
        color: #0f172a;
    }
    .agent-list {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
    }
    .agent-item {
        padding: 12px;
        border-radius: 12px;
        margin-bottom: 8px;
        transition: all 0.2s ease;
        cursor: pointer;
        border: 1px solid transparent;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .agent-item:hover {
        background: #f1f5f9;
        border-color: #e2e8f0;
    }
    .agent-item.active {
        background: #eff6ff;
        border-color: #bfdbfe;
    }
    .agent-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #2563eb;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.1rem;
        box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
    }
    .agent-info {
        flex: 1;
        min-width: 0;
    }
    .agent-info .name {
        font-weight: 700;
        font-size: 0.9rem;
        color: #1e293b;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .agent-info .ticket {
        font-size: 0.75rem;
        color: #64748b;
    }
    .agent-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
    }
</style>

<div class="map-header">
    <div>
        <h1 class="h3 mb-0" style="font-weight:800; color:#0f172a;">Monitoreo en Vivo</h1>
        <p class="text-muted small mb-0">Seguimiento de técnicos en terreno</p>
    </div>
    <div class="map-stats">
        <button id="refresh-map" class="btn btn-sm btn-light border" style="border-radius: 10px; padding: 6px 12px;">
            <i class="bi bi-arrow-clockwise"></i> Actualizar
        </button>
    </div>
</div>

<div class="map-wrapper">
    <div class="agent-sidebar">
        <div class="sidebar-header">
            <h4>Técnicos Activos (<span id="active-agents-count">0</span>)</h4>
        </div>
        <div id="agent-list" class="agent-list">
            <!-- Cargando... -->
            <div class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm mb-2" role="status"></div>
                <div class="small">Buscando técnicos...</div>
            </div>
        </div>
    </div>

    <div class="map-container">
        <div id="map"></div>
    </div>
</div>

<!-- Leaflet JS (Usando jsDelivr porque unpkg está bloqueado por CSP) -->
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar mapa (centrado por defecto, se ajustará con los marcadores)
    var map = L.map('map').setView([0, 0], 2);

    // Si no hay agentes, mostrar un mensaje o intentar centrar en la empresa si existiera
    var noAgentsMsg = L.control({position: 'topright'});
    noAgentsMsg.onAdd = function(map) {
        var div = L.DomUtil.create('div', 'stat-pill bg-warning text-dark border-warning d-none');
        div.id = 'no-agents-alert';
        div.innerHTML = '<i class="bi bi-exclamation-triangle"></i> No hay agentes "En camino" actualmente';
        return div;
    };
    noAgentsMsg.addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var markers = {};
    var agentGroup = L.featureGroup().addTo(map);

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
            sidebarList.innerHTML = '<div class="text-center py-5 text-muted small">No hay técnicos activos</div>';
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
                    <h5 style="margin:0 0 5px; font-size:1rem; font-weight:700;">${loc.name}</h5>
                    <p style="margin:2px 0; font-size:0.85rem;"><strong>Ticket:</strong> #${loc.ticket_number}</p>
                    <p style="margin:2px 0; font-size:0.85rem;"><strong>Estado:</strong> <span class="badge" style="background:#dcfce7; color:#166534; padding:3px 8px; border-radius:4px;">${loc.status}</span></p>
                    <p class="small text-muted" style="margin-top:8px; font-size:0.75rem;">
                        <i class="bi bi-clock"></i> act: ${new Date(loc.updated).toLocaleTimeString()}
                    </p>
                    <a href="tickets.php?id=${loc.ticket_id}" class="btn btn-primary btn-sm w-100 mt-2 text-white" style="font-size:0.75rem;">Ver ticket</a>
                </div>
            `;

            if (markers[loc.staff_id]) {
                markers[loc.staff_id].setLatLng([loc.lat, loc.lng]);
                markers[loc.staff_id].getPopup().setContent(popupContent);
            } else {
                var customIcon = L.divIcon({
                    className: 'custom-div-icon',
                    html: `<div class="marker-pin" style="width:30px; height:30px; border-radius:50% 50% 50% 0; background:#2563eb; position:absolute; transform:rotate(-45deg); left:50%; top:50%; margin:-15px 0 0 -15px; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px rgba(0,0,0,0.3);"><div style="width:14px; height:14px; background:#fff; border-radius:50%;"></div></div><div class="marker-label" style="position:absolute; bottom:-25px; left:50%; transform:translateX(-50%); background:rgba(15,23,42,0.8); color:white; padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:600; white-space:nowrap;">${loc.name.split(' ')[0]}</div>`,
                    iconSize: [30, 42],
                    iconAnchor: [15, 42]
                });

                var marker = L.marker([loc.lat, loc.lng], {icon: customIcon}).bindPopup(popupContent);
                markers[loc.staff_id] = marker;
                agentGroup.addLayer(marker);
            }

            // AGREGAR AL SIDEBAR
            var item = document.createElement('div');
            item.className = 'agent-item';
            item.onclick = () => {
                map.flyTo([loc.lat, loc.lng], 16);
                markers[loc.staff_id].openPopup();
            };
            item.innerHTML = `
                <div class="agent-avatar">${loc.name.charAt(0).toUpperCase()}</div>
                <div class="agent-info">
                    <div class="name">${loc.name}</div>
                    <div class="ticket">#${loc.ticket_number} - ${loc.status}</div>
                </div>
                <div class="agent-status-dot"></div>
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

    document.getElementById('refresh-map').addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
        fetchLocations();
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Actualizar';
        }, 1000);
    });
});
</script>
