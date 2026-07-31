-- ============================================================
-- BUSCONTROL - Esquema PostgreSQL (Supabase)
-- IMPORTANTE: las columnas camelCase requieren comillas dobles
-- SIEMPRE que se usen en SQL (CREATE, SELECT, INSERT, etc.)
-- ============================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL CHECK (rol IN ('admin','conductor','estudiante')),
    "rolId" INT NOT NULL CHECK ("rolId" IN (1,2,3)),
    "codigoEstudiante" VARCHAR(30),
    telefono VARCHAR(30),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    "creadoEn" TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS rutas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS buses (
    id SERIAL PRIMARY KEY,
    placa VARCHAR(20) NOT NULL,
    latitud DOUBLE PRECISION,
    longitud DOUBLE PRECISION,
    velocidad DOUBLE PRECISION DEFAULT 0,
    estado VARCHAR(20) DEFAULT 'inactivo',
    "rutaId" INT REFERENCES rutas(id),
    "conductorId" INT REFERENCES usuarios(id),
    "actualizadoEn" TIMESTAMP
);

CREATE TABLE IF NOT EXISTS paradas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    orden INT NOT NULL,
    "horaEstimada" TIME,
    latitud DOUBLE PRECISION,
    longitud DOUBLE PRECISION,
    "rutaId" INT REFERENCES rutas(id)
);

CREATE TABLE IF NOT EXISTS pagos (
    id SERIAL PRIMARY KEY,
    "usuarioId" INT REFERENCES usuarios(id),
    monto NUMERIC(10,2) NOT NULL,
    "fechaPago" TIMESTAMP NOT NULL DEFAULT NOW(),
    "validoHasta" DATE NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'al_dia' CHECK (estado IN ('al_dia','vencido'))
);

CREATE TABLE IF NOT EXISTS tokens_qr (
    id SERIAL PRIMARY KEY,
    token VARCHAR(120) UNIQUE NOT NULL,
    usado INT NOT NULL DEFAULT 0,
    "estudianteId" INT REFERENCES usuarios(id),
    "creadoEn" TIMESTAMP NOT NULL DEFAULT NOW(),
    "expiraEn" TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS asistencias (
    id SERIAL PRIMARY KEY,
    "estudianteId" INT REFERENCES usuarios(id),
    "viajeId" INT,
    "fechaAbordaje" TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS incidentes (
    id SERIAL PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    descripcion TEXT,
    "busId" INT REFERENCES buses(id),
    "rutaId" INT REFERENCES rutas(id),
    "conductorId" INT REFERENCES usuarios(id),
    estado VARCHAR(20) DEFAULT 'abierto',
    "fechaReporte" TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Índices recomendados
CREATE INDEX IF NOT EXISTS idx_tokens_qr_estudiante ON tokens_qr ("estudianteId");
CREATE INDEX IF NOT EXISTS idx_asistencias_estudiante_fecha ON asistencias ("estudianteId", "fechaAbordaje");
CREATE INDEX IF NOT EXISTS idx_pagos_usuario ON pagos ("usuarioId");
CREATE INDEX IF NOT EXISTS idx_buses_conductor ON buses ("conductorId");
