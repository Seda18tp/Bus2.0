<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
exigir_rol('estudiante', 3);

$nombre = e($_SESSION['nombre'] ?? 'Estudiante');
$token  = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel Estudiante · BUSCONTROL</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="bg-slate-100 min-h-screen">

<header class="bg-slate-800 text-white px-4 py-4 flex items-center justify-between sticky top-0 z-20 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center">
            <i class="fa-solid fa-bus"></i>
        </div>
        <div>
            <p class="font-semibold leading-tight">BUSCONTROL</p>
            <p class="text-xs text-slate-300 leading-tight">Panel del estudiante</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="hidden sm:block text-sm text-slate-300"><?= $nombre ?></span>
        <a href="/logout" class="text-slate-300 hover:text-red-400 transition" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket text-lg"></i>
        </a>
    </div>
</header>

<main class="max-w-2xl mx-auto p-4 space-y-4">

    <!-- Mapa en vivo -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4">
        <h2 class="font-semibold text-slate-800 text-lg mb-3 px-2">
            <i class="fa-solid fa-map-location-dot text-blue-600 mr-2"></i>Ubicación del bus en vivo
        </h2>
        <div id="map" class="w-full h-72 rounded-2xl overflow-hidden"></div>
        <p id="mapInfo" class="text-xs text-slate-400 mt-3 px-2">Buscando bus...</p>
    </div>

    <!-- Generación de pase QR -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 text-center">
        <h2 class="font-semibold text-slate-800 text-lg mb-1">
            <i class="fa-solid fa-qrcode text-blue-600 mr-2"></i>Pase de abordaje
        </h2>
        <p class="text-sm text-slate-500 mb-4">Genera tu código QR y muéstralo al conductor al abordar.</p>

        <div id="qrContainer" class="hidden mb-4">
            <img id="qrImg" src="" alt="Código QR" class="mx-auto rounded-2xl border border-slate-200 w-56 h-56 object-contain">
            <p id="qrExpira" class="text-xs text-slate-400 mt-2"></p>
        </div>

        <button id="btnQr"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-3 px-8 transition">
            <i class="fa-solid fa-bolt mr-2"></i>Generar pase QR
        </button>
        <div id="qrMensaje" class="mt-4"></div>
    </div>

</main>

<script>
const CSRF_TOKEN = <?= json_encode($token) ?>;

// --- Mapa en vivo ---
const map = L.map('map').setView([1.8536, -76.0361], 14); // Vista inicial (ajustable)
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
}).addTo(map);

const busIcon = L.divIcon({
    html: '<div style="background:#2563eb;color:white;border-radius:9999px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.3)"><i class="fa-solid fa-bus"></i></div>',
    className: '', iconSize: [34, 34], iconAnchor: [17, 17]
});
let marker = null;
const mapInfo = document.getElementById('mapInfo');

async function actualizarUbicacion() {
    try {
        const res = await fetch('/api/ubicacion.php');
        const data = await res.json();
        if (data.ok && data.buses && data.buses.length > 0) {
            const bus = data.buses.find(b => b.latitud && b.longitud) || data.buses[0];
            if (bus && bus.latitud && bus.longitud) {
                const latlng = [bus.latitud, bus.longitud];
                if (!marker) {
                    marker = L.marker(latlng, { icon: busIcon }).addTo(map);
                    map.setView(latlng, 15);
                } else {
                    marker.setLatLng(latlng);
                }
                mapInfo.textContent = `Bus ${bus.placa || ''} · Velocidad: ${Math.round(bus.velocidad || 0)} km/h · Ruta: ${bus.rutaNombre || 'N/A'}`;
            } else {
                mapInfo.textContent = 'El bus aún no ha transmitido su ubicación.';
            }
        } else {
            mapInfo.textContent = 'No hay buses disponibles en este momento.';
        }
    } catch (e) {
        mapInfo.textContent = 'Sin conexión con el servidor.';
    }
}
actualizarUbicacion();
setInterval(actualizarUbicacion, 3000);

// --- Generar QR ---
const btnQr = document.getElementById('btnQr');
const qrContainer = document.getElementById('qrContainer');
const qrImg = document.getElementById('qrImg');
const qrExpira = document.getElementById('qrExpira');
const qrMensaje = document.getElementById('qrMensaje');

btnQr.addEventListener('click', async () => {
    btnQr.disabled = true;
    btnQr.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Generando...';
    qrMensaje.innerHTML = '';
    try {
        const res = await fetch('/api/generar_qr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ csrf_token: CSRF_TOKEN })
        });
        const data = await res.json();
        if (data.ok) {
            qrImg.src = data.qr_url;
            qrContainer.classList.remove('hidden');
            qrExpira.textContent = `Válido por ${data.expira_en_min} minutos · Pases restantes hoy: ${data.pases_restantes}`;
        } else {
            qrMensaje.innerHTML = `<div class="bg-red-50 text-red-600 border border-red-200 rounded-2xl p-3 text-sm">
                <i class="fa-solid fa-circle-exclamation mr-1"></i>${data.error}</div>`;
        }
    } catch (e) {
        qrMensaje.innerHTML = `<div class="bg-red-50 text-red-600 border border-red-200 rounded-2xl p-3 text-sm">Error de red</div>`;
    } finally {
        btnQr.disabled = false;
        btnQr.innerHTML = '<i class="fa-solid fa-bolt mr-2"></i>Generar pase QR';
    }
});
</script>
</body>
</html>
