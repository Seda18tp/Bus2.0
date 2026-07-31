<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
exigir_rol('conductor', 2);

$nombre = e($_SESSION['nombre'] ?? 'Conductor');
$token  = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel Conductor · BUSCONTROL</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body class="bg-slate-100 min-h-screen">

<header class="bg-slate-800 text-white px-4 py-4 flex items-center justify-between sticky top-0 z-20 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center">
            <i class="fa-solid fa-bus"></i>
        </div>
        <div>
            <p class="font-semibold leading-tight">BUSCONTROL</p>
            <p class="text-xs text-slate-300 leading-tight">Panel del conductor</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="hidden sm:block text-sm text-slate-300"><?= $nombre ?></span>
        <a href="/logout.php" class="text-slate-300 hover:text-red-400 transition" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket text-lg"></i>
        </a>
    </div>
</header>

<main class="max-w-2xl mx-auto p-4 space-y-4">

    <!-- Estado de transmisión GPS -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-800 text-lg">
                <i class="fa-solid fa-location-crosshairs text-blue-600 mr-2"></i>Transmisión GPS
            </h2>
            <span id="gpsBadge" class="text-xs font-semibold px-3 py-1 rounded-full bg-slate-200 text-slate-600">
                Inactivo
            </span>
        </div>
        <p class="text-sm text-slate-500 mb-4">
            Mantén esta pantalla abierta mientras conduces. Tu ubicación se transmite automáticamente en segundo plano.
        </p>
        <button id="btnGps"
                class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold rounded-2xl py-3 transition">
            <i class="fa-solid fa-play mr-2"></i>Iniciar transmisión
        </button>
        <p id="gpsInfo" class="text-xs text-slate-400 mt-3 text-center"></p>
    </div>

    <!-- Escáner QR -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
        <h2 class="font-semibold text-slate-800 text-lg mb-4">
            <i class="fa-solid fa-qrcode text-blue-600 mr-2"></i>Escanear pase de abordaje
        </h2>
        <div id="qr-reader" class="rounded-2xl overflow-hidden border border-slate-200"></div>
        <div id="qrResultado" class="mt-4"></div>
    </div>

</main>

<script>
const CSRF_TOKEN = <?= json_encode($token) ?>;
let gpsWatchId = null;
let gpsActivo = false;

const btnGps = document.getElementById('btnGps');
const gpsBadge = document.getElementById('gpsBadge');
const gpsInfo = document.getElementById('gpsInfo');

async function enviarUbicacion(lat, lng, vel) {
    try {
        const res = await fetch('/api/ubicacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ latitud: lat, longitud: lng, velocidad: vel || 0, estado: 'activo', csrf_token: CSRF_TOKEN })
        });
        const data = await res.json();
        if (!data.ok) {
            gpsInfo.textContent = 'Error: ' + (data.error || 'no se pudo enviar la ubicación');
        } else {
            gpsInfo.textContent = 'Última actualización: ' + new Date().toLocaleTimeString();
        }
    } catch (e) {
        gpsInfo.textContent = 'Sin conexión, reintentando...';
    }
}

function iniciarGps() {
    if (!navigator.geolocation) {
        alert('Tu navegador no soporta geolocalización.');
        return;
    }
    gpsWatchId = navigator.geolocation.watchPosition(
        (pos) => {
            const { latitude, longitude, speed } = pos.coords;
            enviarUbicacion(latitude, longitude, speed ? speed * 3.6 : 0);
        },
        (err) => { gpsInfo.textContent = 'Error de GPS: ' + err.message; },
        { enableHighAccuracy: true, maximumAge: 2000, timeout: 8000 }
    );
    gpsActivo = true;
    gpsBadge.textContent = 'Transmitiendo';
    gpsBadge.className = 'text-xs font-semibold px-3 py-1 rounded-full bg-emerald-100 text-emerald-700';
    btnGps.innerHTML = '<i class="fa-solid fa-stop mr-2"></i>Detener transmisión';
    btnGps.className = 'w-full bg-red-600 hover:bg-red-700 text-white font-semibold rounded-2xl py-3 transition';
}

function detenerGps() {
    if (gpsWatchId !== null) navigator.geolocation.clearWatch(gpsWatchId);
    gpsActivo = false;
    gpsBadge.textContent = 'Inactivo';
    gpsBadge.className = 'text-xs font-semibold px-3 py-1 rounded-full bg-slate-200 text-slate-600';
    btnGps.innerHTML = '<i class="fa-solid fa-play mr-2"></i>Iniciar transmisión';
    btnGps.className = 'w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold rounded-2xl py-3 transition';
}

btnGps.addEventListener('click', () => gpsActivo ? detenerGps() : iniciarGps());

// --- Lector QR ---
const qrResultado = document.getElementById('qrResultado');
let procesando = false;

function mostrarResultado(ok, mensaje) {
    qrResultado.innerHTML = `
        <div class="rounded-2xl p-4 text-sm font-medium ${ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-600 border border-red-200'}">
            <i class="fa-solid ${ok ? 'fa-circle-check' : 'fa-circle-exclamation'} mr-1"></i>${mensaje}
        </div>`;
}

async function onScanSuccess(decodedText) {
    if (procesando) return;
    procesando = true;
    try {
        const res = await fetch('/api/validar_qr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ token: decodedText, csrf_token: CSRF_TOKEN })
        });
        const data = await res.json();
        if (data.ok) {
            mostrarResultado(true, `${data.mensaje}: ${data.estudiante} (${data.codigo}) · Pase ${data.pases_usados_hoy}/2`);
        } else {
            mostrarResultado(false, data.error);
        }
    } catch (e) {
        mostrarResultado(false, 'Error de red al validar el pase');
    } finally {
        setTimeout(() => { procesando = false; }, 2000);
    }
}

const html5QrCode = new Html5Qrcode('qr-reader');
html5QrCode.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: 250 },
    onScanSuccess
).catch((err) => {
    document.getElementById('qr-reader').innerHTML =
        '<p class="p-4 text-sm text-red-600">No se pudo acceder a la cámara: ' + err + '</p>';
});
</script>
</body>
</html>
