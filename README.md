# BUSCONTROL

Plataforma de gestión, monitoreo GPS en vivo y control de abordaje con QR para transporte de estudiantes universitarios.

## Stack
- PHP 8+ vanilla (sin frameworks)
- PostgreSQL en Supabase
- Tailwind CSS (CDN), FontAwesome 6, Leaflet.js, html5-qrcode
- Despliegue serverless en Vercel

## Estructura del proyecto
```
buscontrol/
├── api/
│   ├── ubicacion.php      # POST (conductor emite GPS) / GET (consulta ubicación)
│   ├── generar_qr.php     # Genera pase QR (estudiante)
│   └── validar_qr.php     # Valida/"quema" el QR (conductor)
├── admin/dashboard.php
├── conductor/dashboard.php
├── estudiante/dashboard.php
├── includes/
│   ├── config.php         # Conexión PDO + cabeceras de seguridad + sesión
│   └── auth.php           # CSRF, rate limiting, roles, sanitización
├── login.php
├── register.php
├── logout.php
├── index.php
├── schema.sql              # Esquema completo de la base de datos
├── vercel.json
└── .env.example
```

## 1. Base de datos (Supabase)
1. Crea un proyecto en Supabase.
2. Ejecuta `schema.sql` en el SQL Editor de Supabase (respeta las comillas dobles en columnas camelCase).
3. Copia la cadena de conexión directa (Settings → Database → Connection string → "URI" modo "Session").

## 2. Variables de entorno
Copia `.env.example` y configura en Vercel (Settings → Environment Variables):
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `APP_ENV=production`

## 3. Despliegue en Vercel
```bash
npm i -g vercel
vercel --prod
```
El archivo `vercel.json` ya declara el runtime `vercel-php` para todos los `.php`.

## 4. Seguridad implementada
- **Contraseñas:** `password_hash()` (bcrypt) + `password_verify()`, nunca texto plano.
- **SQL Injection:** 100% consultas preparadas con PDO (`PDO::ATTR_EMULATE_PREPARES => false`), columnas camelCase siempre entre comillas dobles.
- **CSRF:** token por sesión, validado en formularios HTML y en cada POST de la API (`X-CSRF-Token` o campo `csrf_token`).
- **XSS:** toda salida a HTML pasa por `e()` (htmlspecialchars).
- **Sesiones:** cookies `HttpOnly`, `SameSite=Lax`, `Secure` en producción; regeneración periódica de `session_id()`; `logout.php` destruye solo la sesión activa.
- **Fuerza bruta:** rate limiting básico en login/registro/generación de QR por sesión.
- **Condiciones de carrera del QR:** `validar_qr.php` usa `SELECT ... FOR UPDATE` dentro de una transacción para evitar doble "quemado" de un mismo token.
- **Cabeceras HTTP:** CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`.
- **Autorización:** `exigir_rol()` valida por nombre de rol **o** `rolId`, evitando cierres de sesión accidentales por inconsistencias de mayúsculas/minúsculas.

## 5. Reglas de negocio clave
- Un estudiante solo puede generar/usar **2 pases QR por día** (ida y vuelta).
- El QR expira a los **10 minutos** de generado.
- El QR requiere que el pago del estudiante esté en estado `al_dia`.
- El conductor transmite su GPS vía `POST /api/ubicacion.php` cada vez que el navegador reporta un cambio de posición (`watchPosition`).
- Estudiantes y administradores consultan `GET /api/ubicacion.php` cada 3 segundos.

## 6. Usuarios de prueba
Crea manualmente en Supabase (o vía `register.php` para estudiantes) usuarios con `rol`/`"rolId"`:
- `admin` / 1
- `conductor` / 2
- `estudiante` / 3

Para conductor/admin, inserta directamente en `usuarios` con un hash generado así:
```php
php -r "echo password_hash('TuPassword123', PASSWORD_BCRYPT);"
```

## Notas sobre PostgreSQL Case-Sensitive
Toda columna camelCase (`usuarioId`, `estudianteId`, `fechaAbordaje`, `horaEstimada`, `rutaId`, `busId`, `conductorId`, `validoHasta`, `creadoEn`, `expiraEn`, `rolId`, `codigoEstudiante`, `actualizadoEn`, `viajeId`, `fechaReporte`, `fechaPago`) **debe ir siempre entre comillas dobles** en cualquier SQL, tal como se hizo en todo el código de este proyecto.
