# Seguridad Minera — San Juan

Sistema para registrar, investigar y analizar estadísticamente accidentes, incidentes y condiciones en faenas mineras, alineado con la Ley 19.587, la Ley 24.557, el Dec. 249/07 y las resoluciones SRT 525/15 y 3326/14. El detalle está en [docs/normativa.md](docs/normativa.md).

**Stack:** PHP 8.3 + PostgreSQL 16, con Bootstrap 5, Leaflet y Chart.js. Se despliega en Railway con Docker.

## Módulos

| Página | Qué hace |
|---|---|
| `index.php` | Carga y edición sobre el mapa: datos generales, lesión y tipificación OIT/RENAL, matriz de severidad potencial, denuncia a la ART con control de las 48 h, investigación (Bird/ILCI, porqués) y acciones correctivas. |
| `dashboard.php` | Mapa, filtros, tabla y exportación a CSV (separador `;`, compatible con Excel). |
| `estadisticas.php` | IF, IG, II, IP, DMB, IM, TRIFR; gráfico de control u; pirámide de Bird; Pareto; distribución por hora y día; índices por empresa. |
| `acciones.php` | Seguimiento de acciones, vencimientos y verificación de eficacia. |
| `horas.php` | Horas-hombre y dotación por empresa y mes, que son el denominador de los índices. |
| `informe.php?id=N` | Informe de investigación listo para imprimir o guardar en PDF. |
| `admin.php` | Empresas y usuarios. Roles: ADMIN, SUPERVISOR (carga) y LECTOR. |

## Despliegue en Railway

1. Creá un proyecto con un servicio **PostgreSQL** y un servicio conectado a este repo de GitHub. Railway detecta el `Dockerfile`.
2. Cargá estas variables en el servicio de la app:
   - `DATABASE_URL` = `${{Postgres.DATABASE_URL}}`
   - `ADMIN_EMAIL`, `ADMIN_PASSWORD` y `ADMIN_NAME`, que crean el primer administrador solo si la tabla de usuarios está vacía.
3. En cada arranque, `bin/migrate.php` aplica `db/schema.sql` y `db/seed.sql`. Ambos son idempotentes, así que se pueden correr siempre.
4. Cada `git push` a `main` redespliega la app.

Las fotos se guardan en PostgreSQL (BYTEA) y se reducen a 1600 px, porque el disco de los contenedores de Railway se borra en cada deploy.

## Desarrollo local

Con Docker:

```bash
docker compose up --build      # http://localhost:8080  (admin@local / admin12345)
```

Con XAMPP: activá `extension=pdo_pgsql` y `extension=gd` en `php.ini`, instalá PostgreSQL y corré:

```bash
set DATABASE_URL=postgresql://postgres:clave@localhost:5432/seguridad_mina
php bin/migrate.php
php -S localhost:8080 -t public
```

## Migrar datos desde la versión MySQL

Exportá desde phpMyAdmin las tablas `companies`, `persons`, `incidents`, `incident_companies` e `incident_persons`, y adaptá el SQL a PostgreSQL: quitá las comillas invertidas y `ENGINE=...`, y cambiá `<=>` por `IS NOT DISTINCT FROM`. Los incidentes viejos toman los valores por defecto `tipo_contingencia = INCIDENTE` y `consecuencia = SIN_LESION`, así que conviene revisarlos uno por uno.
