<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
exigir_rol('admin', 1);

$nombre = e($_SESSION['nombre'] ?? 'Administrador');
$token  = csrf_token();

// Estadísticas rápidas para las tarjetas superiores
try {
    $pdo = db();
    $totalEstudiantes = (int)$pdo->query('SELECT COUNT(*) c FROM usuarios WHERE "rolId" = 3')->fetch()['c'];
    $totalBuses = (int)$pdo->query('SELECT COUNT(*) c FROM buses')->fetch()['c'];
    $abordajesHoy = (int)$pdo->query('SELECT COUNT(*) c FROM asistencias WHERE DATE("fechaAbordaje") = CURRENT_DATE')->fetch()['c'];
    $incidentesAbiertos = (int)$pdo->query("SELECT COUNT(*) c FROM incidentes WHERE estado = 'abierto'")->fetch()['c'];
} catch (Throwable $ex) {
    error_log('[BUSCONTROL][ADMIN_STATS] ' . $ex->getMessage());
    $totalEstudiantes = $totalBuses = $abordajesHoy = $incidentesAbiertos = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel Admin · BUSCONTROL</title>
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
            <p class="text-xs text-slate-300 leading-tight">Panel de administración</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="hidden sm:block text-sm text-slate-300"><?= $nombre ?></span>
        <a href="/logout" class="text-slate-300 hover:text-red-400 transition" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket text-lg"></i>
        </a>
    </div>
</header>

<main class="max-w-5xl mx-auto p-4 space-y-4">

    <!-- Tarjetas de estadísticas -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4">
            <p class="text-xs text-slate-500">Estudiantes</p>
            <p class="text-2xl font-bold text-slate-800"><?= $totalEstudiantes ?></p>
        </div>
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4">
            <p class="text-xs text-slate-500">Buses</p>
            <p class="text-2xl font-bold text-slate-800"><?= $totalBuses ?></p>
        </div>
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4">
            <p class="text-xs text-slate-500">Abordajes hoy</p>
            <p class="text-2xl font-bold text-emerald-600"><?= $abordajesHoy ?></p>
        </div>
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4">
            <p class="text-xs text-slate-500">Incidentes abiertos</p>
            <p class="text-2xl font-bold text-red-600"><?= $incidentesAbiertos ?></p>
        </div>
    </div>

    <!-- Mapa general de flota -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4">
        <h2 class="font-semibold text-slate-800 text-lg mb-3 px-2">
            <i class="fa-solid fa-map-location-dot text-blue-600 mr-2"></i>Flota en tiempo real
        </h2>
        <div id="map" class="w-full h-96 rounded-2xl overflow-hidden"></div>
    </div>

    <!-- Tabla de buses -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4 overflow-x-auto">
        <h2 class="font-semibold text-slate-800 text-lg mb-3 px-2">
            <i class="fa-solid fa-list text-blue-600 mr-2"></i>Estado de buses
        </h2>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200">
                    <th class="py-2 px-2">Placa</th>
                    <th class="py-2 px-2">Ruta</th>
                    <th class="py-2 px-2">Estado</th>
                    <th class="py-2 px-2">Velocidad</th>
                    <th class="py-2 px-2">Última actualización</th>
                </tr>
            </thead>
            <tbody id="tablaBuses"></tbody>
        </table>
    </div>

</main>

<script>
const map = L.map('map').setView([1.8536, -76.0361], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
}).addTo(map);

const busIcon = L.divIcon({
    html: '<div style="background:#2563eb;color:white;border-radius:9999px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.3)"><i class="fa-solid fa-bus"></i></div>',
    className: '', iconSize: [32, 32], iconAnchor: [16, 16]
});

const marcadores = {};
const tablaBuses = document.getElementById('tablaBuses');

function badgeEstado(estado) {
    const map = {
        activo: 'bg-emerald-100 text-emerald-700',
        detenido: 'bg-amber-100 text-amber-700',
        inactivo: 'bg-slate-200 text-slate-600'
    };
    return `<span class="text-xs font-semibold px-2.5 py-1 rounded-full ${map[estado] || map.inactivo}">${estado || 'inactivo'}</span>`;
}

async function actualizarFlota() {
    try {
        const res = await fetch('/api/ubicacion.php');
        const data = await res.json();
        if (!data.ok) return;

        tablaBuses.innerHTML = '';

        data.buses.forEach(bus => {
            if (bus.latitud && bus.longitud) {
                const latlng = [bus.latitud, bus.longitud];
                if (!marcadores[bus.id]) {
                    marcadores[bus.id] = L.marker(latlng, { icon: busIcon }).addTo(map)
                        .bindPopup(`<b>${bus.placa}</b><br>Ruta: ${bus.rutaNombre || 'N/A'}`);
                } else {
                    marcadores[bus.id].setLatLng(latlng);
                }
            }

            const fila = document.createElement('tr');
            fila.className = 'border-b border-slate-100';
            fila.innerHTML = `
                <td class="py-2 px-2 font-medium text-slate-800">${bus.placa || '-'}</td>
                <td class="py-2 px-2">${bus.rutaNombre || '-'}</td>
                <td class="py-2 px-2">${badgeEstado(bus.estado)}</td>
                <td class="py-2 px-2">${Math.round(bus.velocidad || 0)} km/h</td>
                <td class="py-2 px-2 text-slate-400">${bus.actualizadoEn ? new Date(bus.actualizadoEn).toLocaleTimeString() : '-'}</td>
            `;
            tablaBuses.appendChild(fila);
        });
    } catch (e) {
        console.error('Error al actualizar flota', e);
    }
}
actualizarFlota();
setInterval(actualizarFlota, 3000);
</script>
</body>
</html>
