# Mapeo técnico del backend SIMRED / SEDALP

> **Versión vigente: 1 de septiembre de 2026.**
>
> Estado auditado: árbol de trabajo actual, incluidos cambios todavía no confirmados en Git. No se inspeccionaron internamente vendor ni node_modules y no se reproducen secretos.

## Resumen vigente

SIMRED dispone de un backend API-first en Laravel 13 y PostgreSQL. El alcance terminado comprende JWT, roles/permisos, CRUD integral de usuarios vinculados a personal, catálogos organizacionales y CRUD de noticias con publicación, imágenes, videos y papelera.

| Elemento | Estado vigente |
|---|---:|
| Modelos / controladores propios | 9 / 15 |
| Form Requests / API Resources | 30 / 8 |
| Servicios / DTOs / Enums / Jobs | 5 / 1 / 8 / 1 |
| Migraciones | 16: 14 aplicadas y 2 pendientes |
| Tablas PostgreSQL locales | 21 |
| Endpoints API | 54: 49 administrativos, 4 de autenticación y 1 de estado |
| Permisos / roles | 57 / 5 |
| Archivos de prueba | 19 |
| Pruebas / aserciones | 226 / 942, todas aprobadas |

**Estado funcional:** el CRUD solicitado de usuarios y noticias está implementado y probado.

**Estado operativo:** antes de usar esta versión sobre desarrollo o producción deben aplicarse las dos migraciones pendientes. La gestión de .env permanece bajo decisión del propietario.

## Stack y arquitectura vigentes

| Componente | Versión/configuración |
|---|---|
| PHP / Laravel | 8.5.9 / 13.23.0 |
| PostgreSQL | 18.4, conexión pgsql |
| JWT Auth / Spatie Permission | 2.9.2 / 8.3.0 |
| Pruebas | Pest 5 / PHPUnit 13 |

Sanctum no forma parte del proyecto y la base local ya no contiene personal_access_tokens.

Las rutas se separan en estado público, autenticación JWT y administración protegida por auth:api, account.active y can:*. Las operaciones multitabla viven en servicios transaccionales; Form Requests concentran autorización/validación y Resources definen la salida para Vue.

## Dominio vigente

- User: credenciales, rol, estado, versión de token, soft delete y vínculo con personal.
- StaffMember: nombres, apellidos, CI, contacto, unidad, cargo, profesión y estado laboral.
- OrganizationalUnit, Position y Profession: catálogos organizacionales.
- News: TipTap, slug, publicación, autoría y soft delete.
- NewsImage y NewsVideo: multimedia ordenada.
- AccessStateChange: historial de cambios de acceso.

RoleName contiene super_admin, director, responsable, tecnico y comunicador. Los otros enums controlan 57 permisos, estados de cuenta/acceso, acciones auditables, estados editoriales y formatos de imagen.

Los autores y editores de noticias se cargan incluyendo usuarios eliminados lógicamente, conservando la autoría.

## Migraciones vigentes

Hay 16 migraciones: 14 aplicadas y dos pendientes.

1. 2026_08_31_040308_add_access_state_to_users_table agrega account_status y token_version con defaults, índice y CHECK.
2. 2026_08_31_040309_create_access_state_changes_table crea actor, usuario/personal objetivo, acción, motivo, metadatos, fecha e índices.

El código ya utiliza esas columnas y tabla. Aplicarlas es obligatorio antes de usar esta versión con la base local o productiva.

Ya están aplicados el CHECK de fecha para noticias publicadas, el UNIQUE funcional de CI/complemento, índices GIN trigram de noticias, índices de FK de personal y restricciones de posiciones multimedia.

La base local contiene 21 tablas. Después de crear auditoría tendrá 22 contando migrations.

## Autenticación vigente

| Aspecto | Implementación |
|---|---|
| Guard / driver | api / jwt |
| Claims | sub y claim persistente ver |
| Access TTL / refresh TTL | 60 / 20.160 minutos por defecto |
| Login / refresh throttle | 5 por correo+IP / 30 por IP |

El acceso efectivo combina soft delete, account_status y StaffMember.active. Refresh admite access tokens expirados dentro de refresh_ttl y después valida usuario, estado y token_version.

Cambiar contraseña, estado de cuenta, estado laboral, eliminar o restaurar incrementa token_version y revoca JWT anteriores.

## Roles y permisos vigentes

| Función | super_admin | comunicador |
|---|---:|---:|
| CRUD de usuarios | sí | no |
| Estado/restauración de usuarios | sí | no |
| CRUD de personal y catálogos | sí | no |
| CRUD y publicación de noticias | sí | sí |
| Imágenes y videos | sí | sí |
| Papelera/force delete de noticias | sí | no |

El comunicador recibe solo news.view, news.create, news.update, news.delete y news.publish.

No se permite autoeliminación, autosuspensión, baja laboral propia ni autodegradación de superadministrador. Un usuario normal no puede gestionar superadministradores. Solo super_admin restaura usuarios y no existe force delete de usuarios.

## API vigente

Las 49 rutas administrativas comparten auth:api, account.active y scopeBindings().

### Usuarios — 9 rutas

- GET/POST /api/admin/users;
- GET /api/admin/users/trash;
- GET/PATCH/DELETE /api/admin/users/{user};
- PUT /api/admin/users/{user}/role;
- PATCH /api/admin/users/{user}/status;
- POST /api/admin/users/{user}/restore.

La creación recibe staff_member_id de personal activo existente o un objeto staff_member. En el segundo caso crea personal, cuenta y rol en una sola transacción. La edición acepta personal parcial, pero prohíbe staff_member.active, que tiene endpoint dedicado.

El listado busca por correo, nombres, apellidos, CI, cargo y profesión; filtra por rol, cuenta, estado laboral, unidad, cargo y profesión.

### Personal — 6 rutas

CRUD en /api/admin/staff-members y PATCH /api/admin/staff-members/{staffMember}/status. Desactivar personal revoca JWT; personal vinculado a usuarios activos o eliminados lógicamente no puede eliminarse.

### Noticias — 17 rutas

CRUD, papelera, restauración, force delete, imágenes, videos y reordenamiento. Publicar, retirar publicación o cambiar su fecha exige news.publish. El título no regenera slug y la papelera expone deletedAt.

### Catálogos y acceso — 17 rutas

Cinco rutas por unidad, cargo y profesión, además de dos catálogos de solo lectura para roles/permisos.

## Servicios, multimedia y colas vigentes

| Servicio | Responsabilidad |
|---|---|
| UserService | filtros, alta integral, edición, rol, estado, baja, restauración y auditoría |
| StaffMemberStatusService | estado laboral, protección y revocación |
| NewsService | alta, edición, publicación, baja, slug y locks |
| NewsTrashService | restauración, force delete y limpieza |
| ImageService | UUID, resize, formatos, almacenamiento y eliminación |

Se usan advisory locks para slug, lockForUpdate en operaciones sensibles, locks para posiciones y UNIQUE como defensa final.

ImageService comprueba los booleanos de Storage. CleanupImageFiles es único, tiene cinco intentos, backoff progresivo, timeout de 30 segundos y se encola ante escrituras parciales, rollback o eliminación fallida.

## Seeders vigentes

1. RolesAndPermissionsSeeder: 57 permisos, cinco roles y matrices.
2. SuperAdminSeeder: valida configuración, restaura cuenta, actualiza contraseña y asigna rol transaccionalmente.
3. NewsPermissionsSeeder: compatibilidad para sembrar al comunicador aisladamente.

.env.example documenta PostgreSQL, superadministrador, JWT y MEDIA_DISK sin secretos reales.

## Pruebas vigentes

| Área | Pruebas |
|---|---:|
| Estado y ejemplos | 3 |
| Autenticación / autorización | 30 |
| Usuarios y ciclo de acceso | 34 |
| Personal, estado y catálogos | 83 |
| SuperAdminSeeder | 6 |
| Noticias y multimedia | 64 |
| ImageService y cleanup job | 6 |
| **Total** | **226** |

Ejecución 1/09/2026: **226 passed, 942 assertions**. Laravel Pint aprobó y git diff --check no reportó errores.

## Hallazgos vigentes

### Corregidos

- protección integral de superadministradores;
- revocación por cuenta o personal inactivo;
- refresh dentro de refresh_ttl y throttle dedicado;
- fecha de publicación protegida en Request/PostgreSQL;
- locks de slug/posiciones e índices de rendimiento;
- detección de fallos de Storage y cleanup job con reintentos;
- defaults de estado, comunicador integrado y .env.example completo;
- tabla residual de Sanctum eliminada y cobertura HTTP de catálogos/seguridad.

### Pendientes operativos — alta prioridad

1. Aplicar las dos migraciones pendientes; el código ya depende de ellas.
2. .env y .env.testing continúan versionados. Retiro y rotación quedan bajo decisión del propietario.

### Fuera del alcance actual

- API pública de noticias y endpoint de consulta de auditoría;
- hero banners, misión, visión, objetivos e imágenes institucionales;
- módulos geográficos/asistencias que hoy solo tienen permisos.

### Mejoras no bloqueantes

- congelar snake_case o camelCase para Vue;
- agregar factories de People/noticias/multimedia;
- monitorear workers y jobs fallidos.

## Contrato funcional vigente

1. Laravel es la fuente de verdad y Vue debe alinearse con él.
2. super_admin administra usuarios y noticias.
3. comunicador administra solo noticias y multimedia.
4. La baja de usuarios es lógica; no hay eliminación física por API.
5. Cuenta y estado laboral son independientes y ambos determinan acceso.
6. Operaciones sensibles exigen motivo y revocan tokens anteriores.

## Próximos pasos operativos

1. Respaldar la base.
2. Revisar y autorizar las dos migraciones pendientes.
3. Ejecutar php artisan migrate cuando el propietario lo decida.
4. Configurar o rotar secretos sin versionarlos.
5. Mantener worker activo si la cola no usa sync.
6. Alinear Vue 3 con este contrato.

## Evidencia final vigente

- 54 endpoints API;
- 16 migraciones: 14 aplicadas y 2 pendientes;
- 21 tablas locales;
- 57 permisos y cinco roles;
- 19 archivos, 226 pruebas y 942 aserciones;
- .env no fue modificado y no se reprodujeron secretos;
- vendor y node_modules quedaron fuera del análisis interno.

---

<details>
<summary><strong>Anexo histórico: auditoría del 30 de agosto de 2026, anterior a las correcciones</strong></summary>

> Las cifras y hallazgos siguientes describen un estado anterior y se conservan solo como trazabilidad.

## Auditoría histórica

> Actualizado: 30 de agosto de 2026.
>
> Estado auditado: árbol de trabajo actual, incluidos cambios todavía no confirmados en Git.
>
> Alcance: código propio no ignorado, configuración, rutas, migraciones, esquema PostgreSQL, modelos, autenticación, autorización, validaciones, Resources, servicios, multimedia, seeders, factories y pruebas. Se respetó `.gitignore`; no se inspeccionó internamente `vendor/`, `node_modules/`, logs, cachés ni builds. No se reproducen valores de secretos.
>
> Evidencia: `composer.json`, `composer.lock`, `routes/api.php`, `bootstrap/app.php`, `app/`, `database/`, `config/`, `tests/`, `phpunit.xml`, `route:list`, `migrate:status`, `db:show` y `php artisan test --compact`.

## 1. Resumen ejecutivo

SEDALP es un backend administrativo API-first en Laravel 13 y PostgreSQL. Implementa JWT, autorización con Spatie Permission, catálogos de personal, cuentas vinculadas a personal, noticias, imágenes, videos y papelera.

| Elemento | Estado actual |
|---|---:|
| Modelos propios | 8 |
| Controladores propios, incluido el base | 12 |
| Form Requests / API Resources | 20 / 8 |
| Servicios / DTOs / Enums | 3 / 1 / 5 |
| Migraciones presentes | 13, todas aplicadas localmente |
| Tablas definidas por migraciones | 20; 21 contando `migrations` |
| Tablas observadas en la base local | 22; incluye `personal_access_tokens` residual |
| Endpoints API efectivos | 50, sin duplicados |
| Archivos de prueba | 9 |
| Pruebas / aserciones | 92 / 361, todas aprobadas |

La funcionalidad cubierta está operativa, pero el repositorio no está listo para producción: hay archivos de entorno versionados con secretos y brechas altas en protección de `super_admin`, revocación de personal inactivo y detección de fallos al limpiar multimedia.

**Veredicto: Requiere correcciones importantes.**

## 2. Stack tecnológico

| Componente | Versión/configuración | Evidencia |
|---|---|---|
| PHP requerido / local | `^8.3` / 8.5.9 | `composer.json`, `php -v` |
| Laravel requerido / bloqueado | `^13.8` / 13.23.0 | Composer |
| PostgreSQL local | 18.4, conexión `pgsql` | `config/database.php`, `db:show` |
| JWT Auth | 2.9.2 | Composer, `config/jwt.php` |
| Spatie Permission | 8.3.0 | Composer, `config/permission.php` |
| Intervention Image | 4.3.1; integración 4.1.1 | Composer |
| Pest / plugin Laravel | 5.0.3 / 5.0.1 | Composer |
| Vite / Tailwind | 8.2.0 / 4.3.3 | `package-lock.json` |

Desarrollo: Boost 2.4.13, Pint 1.30.3, Pail 1.2.7, Pao 1.1.3, Tinker 3.0.2, Faker y Mockery. El frontend es solo la plantilla mínima de Laravel.

### Sanctum

Sanctum ya no está en Composer, no existe `config/sanctum.php`, `User` no usa `HasApiTokens`, no hay rutas Sanctum y su migración fue eliminada. **Sanctum no está activo.** La base local conserva `personal_access_tokens`, residuo histórico que no forma parte del esquema reproducible actual.

## 3. Arquitectura

```text
Cliente HTTP
    ▼
routes/api.php
    ├── /api/estado                    pública
    ├── /api/auth/*                    JWT
    └── /api/admin/*                   auth:api + can:* + scopeBindings
          ├── AccessControl            usuarios, roles, permisos
          ├── People                   unidades, cargos, profesiones, personal
          └── Communication            noticias, imágenes, videos, papelera
                    ├── NewsTrashService
                    └── ImageService

Eloquent ──► PostgreSQL
JWT      ──► User implements JWTSubject
Spatie   ──► guard api
Storage  ──► disco media configurable
```

La organización es por dominio dentro de Controllers, Requests, Resources, Models y Services. `bootstrap/app.php` registra web/API/consola, `/up` y fuerza JSON en `api/*`. `AppServiceProvider` configura throttle y bypass de `super_admin`.

```text
app/
├── DTOs/Media/ImageOptions.php
├── Enums/{Auth,Communication,Media}/
├── Http/{Controllers,Requests,Resources}/
├── Models/{User,People,Communication}/
├── Providers/AppServiceProvider.php
└── Services/{AccessControl,Communication,Media}/
database/{migrations,seeders,factories}/
routes/{api,web,console}.php
tests/Feature/Api/
```

## 4. Modelos y dominio

| Modelo | Traits / tabla | Fillable y casts | Relaciones/comportamiento |
|---|---|---|---|
| `User` | `Authenticatable`, factory, roles, notificaciones, SoftDeletes; `users` | personal, email, password; fecha y `hashed` | pertenece a personal; crea/edita noticias; `JWTSubject` |
| `OrganizationalUnit` | factory, SoftDeletes | nombre, código, descripción, activo; boolean | tiene personal |
| `Position` | factory, SoftDeletes | nombre, descripción, activo; boolean | tiene personal |
| `Profession` | factory, SoftDeletes | nombre, activo; boolean | tiene personal |
| `StaffMember` | factory, SoftDeletes | datos, contacto, tres FK, activo; fecha/boolean | pertenece a unidad/cargo/profesión; tiene usuario |
| `News` | factory, SoftDeletes; tabla `news` | contenido editorial; JSON array, fecha, `NewsStatus` | creador/editor; multimedia ordenada; búsqueda `ILIKE` |
| `NewsImage` | factory | noticia, UUID, alt, caption, posición | pertenece a noticia; directorio `communication/news` |
| `NewsVideo` | factory | noticia, URL, título, posición | pertenece a noticia |

Las relaciones coinciden con las FK. Las asignaciones polimórficas de Spatie no tienen FK hacia `users`, por diseño.

| Tipo | Contenido |
|---|---|
| `RoleName` | `super_admin`, `director`, `responsable`, `tecnico` |
| `PermissionName` | 45 permisos base; noticias están en su seeder |
| `NewsStatus` | `draft`, `published`, `archived` |
| `ImageFormat` | `webp`, `png`, `jpeg` |
| `ImageResizeMode` | `none`, `scale_down`, `cover_down` |
| `ImageOptions` | directorio, formatos, resize, dimensiones y calidad; valida todos los valores |

## 5. Esquema PostgreSQL canónico

Representa las 13 migraciones presentes. Los timestamps Laravel son `timestamp without time zone` nullable salvo indicación. Todas las FK usan `ON UPDATE NO ACTION`.

### 5.1 Identidad

#### `users`

| Columna | Tipo | Null | Restricción/default |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `staff_member_id` | bigint | sí | FK `staff_members`, UNIQUE, DELETE RESTRICT |
| `email` | varchar(255) | no | UNIQUE |
| `email_verified_at` | timestamp | sí | — |
| `password` | varchar(255) | no | — |
| `remember_token` | varchar(100) | sí | — |
| `created_at`, `updated_at` | timestamp | sí | — |
| `deleted_at` | timestamp | sí | SoftDeletes |

`users.name` fue eliminado por la migración de 10/08/2026.

#### `password_reset_tokens`

| Columna | Tipo | Null | Restricción |
|---|---|---:|---|
| `email` | varchar(255) | no | PK |
| `token` | varchar(255) | no | — |
| `created_at` | timestamp | sí | — |

#### `sessions`

| Columna | Tipo | Null | Restricción |
|---|---|---:|---|
| `id` | varchar(255) | no | PK |
| `user_id` | bigint | sí | índice, sin FK |
| `ip_address` | varchar(45) | sí | — |
| `user_agent` | text | sí | — |
| `payload` | text | no | — |
| `last_activity` | integer | no | índice |

### 5.2 Catálogos y personal

#### `positions`

| Columna | Tipo | Null | Restricción/default |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `name` | varchar(100) | no | UNIQUE |
| `description` | varchar(150) | sí | — |
| `active` | boolean | no | DEFAULT true |
| timestamps | timestamp | sí | — |
| `deleted_at` | timestamp | sí | SoftDeletes |

#### `professions`

| Columna | Tipo | Null | Restricción/default |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `name` | varchar(150) | no | UNIQUE |
| `active` | boolean | no | DEFAULT true |
| timestamps | timestamp | sí | — |
| `deleted_at` | timestamp | sí | SoftDeletes |

#### `organizational_units`

| Columna | Tipo | Null | Restricción/default |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `name` | varchar(150) | no | UNIQUE |
| `code` | varchar(50) | no | UNIQUE |
| `description` | varchar(255) | sí | — |
| `active` | boolean | no | DEFAULT true, índice |
| timestamps | timestamp | sí | — |
| `deleted_at` | timestamp | sí | SoftDeletes |

#### `staff_members`

| Columna | Tipo | Null | Restricción/default |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `first_names` | varchar(100) | no | — |
| `paternal_surname`, `maternal_surname` | varchar(80) | sí | índice compuesto entre ambos |
| `ci` | varchar(15) | no | CHECK `^[0-9]{1,15}$` |
| `ci_complement` | varchar(4) | sí | CHECK null o `^[A-Z0-9]{2}$` |
| `birth_date` | date | sí | — |
| `phone` | varchar(20) | sí | — |
| `email` | varchar(254) | sí | — |
| `position_id` | bigint | no | FK `positions`, DELETE RESTRICT |
| `profession_id` | bigint | no | FK `professions`, DELETE RESTRICT |
| `organizational_unit_id` | bigint | no | FK `organizational_units`, DELETE RESTRICT |
| `active` | boolean | no | DEFAULT true |
| timestamps | timestamp | sí | — |
| `deleted_at` | timestamp | sí | SoftDeletes |

Índice UNIQUE funcional `(ci, COALESCE(ci_complement, ''))`, incluyendo soft-deleted. Las tres FK no tienen índices explícitos.

### 5.3 Roles y permisos

| Tabla | Columnas | PK/FK/índices |
|---|---|---|
| `permissions` | id, name, guard_name, timestamps | PK; UNIQUE `(name, guard_name)` |
| `roles` | id, name, guard_name, timestamps | PK; UNIQUE `(name, guard_name)`; sin teams |
| `model_has_permissions` | permission_id, model_type, model_id | PK compuesta; FK permiso CASCADE; índice `(model_id, model_type)` |
| `model_has_roles` | role_id, model_type, model_id | PK compuesta; FK rol CASCADE; índice `(model_id, model_type)` |
| `role_has_permissions` | permission_id, role_id | PK compuesta; ambas FK CASCADE |

IDs bigint; nombres/tipos varchar(255); timestamps nullable.

### 5.4 Noticias

#### `news`

| Columna | Tipo | Null | Restricción/default |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `created_by` | bigint | no | FK `users`, RESTRICT, índice |
| `updated_by` | bigint | sí | FK `users`, RESTRICT, índice |
| `slug` | varchar(255) | no | UNIQUE |
| `title` | varchar(255) | no | — |
| `subtitle` | varchar(255) | sí | — |
| `excerpt`, `description` | text | no | — |
| `content` | jsonb | no | documento TipTap |
| `published_at` | date | sí | — |
| `status` | varchar(20) | no | DEFAULT `draft`; CHECK de tres estados |
| timestamps | timestamp | sí | — |
| `deleted_at` | timestamp | sí | SoftDeletes |

Índice compuesto `(status, published_at)`.

#### `news_images`

| Columna | Tipo | Null | Restricción |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `news_id` | bigint | no | FK `news`, DELETE CASCADE |
| `filename` | varchar(36) | no | UNIQUE; UUID sin extensión |
| `alt` | varchar(255) | no | — |
| `caption` | text | sí | — |
| `position` | integer | no | CHECK `>= 0` |
| timestamps | timestamp | sí | — |

UNIQUE `(news_id, position)`.

#### `news_videos`

| Columna | Tipo | Null | Restricción |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `news_id` | bigint | no | FK `news`, DELETE CASCADE |
| `youtube_url` | varchar(2048) | no | — |
| `title` | varchar(255) | no | — |
| `position` | integer | no | CHECK `>= 0` |
| timestamps | timestamp | sí | — |

UNIQUE `(news_id, position)`.

### 5.5 Infraestructura

| Tabla | Columnas/restricciones principales |
|---|---|
| `cache` | key varchar PK, value text, expiration bigint indexado |
| `cache_locks` | key varchar PK, owner varchar, expiration bigint indexado |
| `jobs` | id bigint PK, queue indexado, payload, attempts smallint y marcas integer |
| `job_batches` | id varchar PK, nombre, contadores, IDs fallidos, opciones nullable y marcas integer |
| `failed_jobs` | id PK, uuid UNIQUE, conexión, cola, payload, excepción, failed_at default current; índice `(connection, queue, failed_at)` |
| `migrations` | administrada por Laravel; no se crea en migraciones de aplicación |

### 5.6 Relaciones

```text
organizational_units 1 ─── N staff_members
positions            1 ─── N staff_members
professions          1 ─── N staff_members
staff_members        1 ─ 0..1 users
users                N ─── N roles
users                N ─── N permissions
roles                N ─── N permissions
users                1 ─── N news (created_by)
users                1 ─── N news (updated_by, opcional)
news                 1 ─── N news_images
news                 1 ─── N news_videos
```

## 6. Autenticación JWT

| Aspecto | Implementación |
|---|---|
| Guard / driver / provider | `api` / `jwt` / Eloquent `User` |
| Identificador / claims | PK como `sub`; sin claims propios |
| Algoritmo / TTL | HS256 / 60 minutos por defecto |
| Refresh TTL | 20.160 minutos |
| Blacklist | activa |
| Secreto | `JWT_SECRET`, valor no documentado |
| Throttle | 5/min por correo en minúsculas + IP |

```text
login ──► Bearer token ──► me
                     ├──► refresh
                     └──► logout ──► blacklist
```

El login usa validación inline. No comprueba `StaffMember.active` ni soft delete del personal; solo credenciales de un `User` no eliminado.

`refresh` está detrás de `auth:api`: renueva tokens vigentes, pero el middleware rechaza tokens expirados antes del controlador. La ventana `refresh_ttl` no actúa como ventana posterior a expiración con esta ruta.

## 7. Roles y permisos

Spatie usa guard `api`, sin teams. `Gate::before` concede toda ability a `super_admin`.

| Rol | Permisos explícitos |
|---|---|
| `super_admin` | ninguno; bypass global |
| `director` | `staff.view`, vistas geográficas, asignaciones regionales CRUD parcial, asistencia view |
| `responsable` | vistas geográficas y asistencia view |
| `tecnico` | vistas geográficas, asignación view, asistencia view/create/update |
| `comunicador` | `news.view/create/update/delete/publish` |

Se crean 53 permisos: 45 del enum y 8 de noticias. Todos los usados por `can:*`, Form Requests y `news.publish` existen.

Permisos sin módulo HTTP: `regions.*`, `provinces.*`, `municipalities.*`, `region_assignments.view/create/update`, `technical_assistances.*` y `roles.create/update/delete`.

Ningún rol normal recibe administración de usuarios, catálogos People, catálogos de acceso o papelera. En la siembra estándar, solo superadmin accede; director únicamente puede listar personal entre las APIs actuales.

Protecciones actuales: no-superadmin no asigna `super_admin`; no hay autoeliminación; no-superadmin no elimina superadmin. No se protege la actualización de credenciales, degradación de rol, último superadmin ni auto-degradación.

## 8. API

Base `/api`. Las 45 rutas administrativas comparten `auth:api` y `scopeBindings()`. Resultado de Artisan: 50 endpoints API, 0 duplicados y 0 acciones inexistentes.

Errores transversales: 401, 403, 404, 422 y 429. DELETE de catálogos/personal puede devolver 409.

### Estado y autenticación

| Método/endpoint | Acción | Protección | Resultado |
|---|---|---|---|
| GET `/api/estado` | Closure | pública | 200 estado |
| POST `/api/auth/login` | `AuthController@login` | throttle | 200 token; 401/422/429 |
| GET `/api/auth/me` | `me` | JWT | 200 usuario/autorización |
| POST `/api/auth/logout` | `logout` | JWT | 200, blacklist |
| POST `/api/auth/refresh` | `refresh` | JWT | 200 token |

### Noticias y papelera

| Método/endpoint | Acción / Request | Permiso | Resultado |
|---|---|---|---|
| GET `/api/admin/news` | `NewsController@index`; `IndexNewsRequest` | `news.view` | 200 paginado |
| POST `/api/admin/news` | `store`; `StoreNewsRequest` | `news.create`; publicar suma `news.publish` | crea, 200 actual |
| GET `/api/admin/news/{news}` | `show` | `news.view` | 200 |
| PUT `/api/admin/news/{news}` | `update`; `UpdateNewsRequest` | `news.update` | 200 |
| PATCH `/api/admin/news/{news}` | igual | igual | 200 |
| DELETE `/api/admin/news/{news}` | `destroy` | `news.delete` | 200, soft delete |
| GET `/api/admin/news/trash` | `NewsTrashController@index`; `IndexNewsTrashRequest` | `news.trash.view` | 200 |
| POST `/api/admin/news/{news}/restore` | `restore` | `news.restore` | 200 |
| DELETE `/api/admin/news/{news}/force` | `forceDelete` | `news.force_delete` | 200 |

### Imágenes y videos

| Método/endpoint | Acción / Request | Permiso |
|---|---|---|
| POST `/api/admin/news/{news}/images` | `NewsImageController@store`; `StoreNewsImagesRequest` | `news.update` |
| PATCH `/api/admin/news/{news}/images/{image}` | `update`; `UpdateNewsImageRequest` | `news.update` |
| PUT `/api/admin/news/{news}/images/reorder` | `reorder`; `ReorderNewsMediaRequest` | `news.update` |
| DELETE `/api/admin/news/{news}/images/{image}` | `destroy` | `news.update` |
| POST `/api/admin/news/{news}/videos` | `NewsVideoController@store`; `StoreNewsVideoRequest` | `news.update` |
| PATCH `/api/admin/news/{news}/videos/{video}` | `update`; `UpdateNewsVideoRequest` | `news.update` |
| PUT `/api/admin/news/{news}/videos/reorder` | `reorder`; `ReorderNewsMediaRequest` | `news.update` |
| DELETE `/api/admin/news/{news}/videos/{video}` | `destroy` | `news.update` |

Todos responden 200 en éxito; reorder exige el conjunto completo y posiciones `0..N-1`.

### Catálogos People

| Recurso base | GET lista | POST | GET uno | PATCH | DELETE |
|---|---|---|---|---|---|
| `/api/admin/organizational-units` | `.view`, 200 | `.create`, 201 | `.view`, 200 | `.update`, 200 | `.delete`, 204/409 |
| `/api/admin/positions` | `.view`, 200 | `.create`, 201 | `.view`, 200 | `.update`, 200 | `.delete`, 204/409 |
| `/api/admin/professions` | `.view`, 200 | `.create`, 201 | `.view`, 200 | `.update`, 200 | `.delete`, 204/409 |

Los endpoints individuales son `/{organizationalUnit}`, `/{position}` y `/{profession}`. Acciones: `index/store/show/update/destroy`; los POST/PATCH usan sus `Store*Request`/`Update*Request`. Listados aceptan search, active y per_page 1–100, pero no tienen Form Request.

### Personal

| Método/endpoint | Acción / Request | Permiso | Resultado |
|---|---|---|---|
| GET `/api/admin/staff-members` | `index` | `staff.view` | 200 paginado |
| POST `/api/admin/staff-members` | `store`; `StoreStaffMemberRequest` | `staff.create` | 201 |
| GET `/api/admin/staff-members/{staffMember}` | `show` | `staff.view` | 200 |
| PATCH misma ruta | `update`; `UpdateStaffMemberRequest` | `staff.update` | 200 |
| DELETE misma ruta | `destroy` | `staff.delete` | 204/409 |

Filtros: texto, tres FK, activo y paginación; no tienen Form Request.

### Usuarios y acceso

| Método/endpoint | Acción / Request | Permiso | Resultado |
|---|---|---|---|
| GET `/api/admin/users` | `UserController@index` | `users.view` | 200 |
| POST `/api/admin/users` | `store`; `StoreUserRequest` | `users.create` + `roles.assign` | 201 |
| GET `/api/admin/users/{user}` | `show` | `users.view` | 200 |
| PATCH misma ruta | `update`; `UpdateUserRequest` | `users.update` | 200 |
| DELETE misma ruta | `destroy` | `users.delete` | 204/403/422 |
| PUT `/api/admin/users/{user}/role` | `updateRole`; `UpdateUserRoleRequest` | `roles.assign` | 200 |
| GET `/api/admin/access/roles` | `AccessCatalogController@roles` | `roles.view` | 200 |
| GET `/api/admin/access/permissions` | `permissions` | `permissions.view` | 200 |

## 9. Requests y validaciones

### Usuarios

| Request | Reglas clave |
|---|---|
| `StoreUserRequest` | personal requerido, activo/no eliminado/existente y único; email RFC 255 único; password confirmada 12+ compleja; rol guard `api`; solo superadmin asigna superadmin |
| `UpdateUserRequest` | email/password `sometimes`, mismas reglas; normaliza email |
| `UpdateUserRoleRequest` | rol existente; bloquea asignar superadmin a no-superadmin |

Flujo `StaffMember → User → Role → Permissions`. Creación transaccional; `syncRoles` deja un rol. DB permite `staff_member_id` null para cuentas técnicas, pero la API lo exige.

### Personal y catálogos

- máximos HTTP coinciden con DB: cargo 100/150; profesión 150; unidad 150/50/255; nombres 100; apellidos 80; CI 15; complemento 2; teléfono 20; email 254;
- CI/complemento replican CHECK y validan unicidad contra soft-deleted;
- FK deben existir, estar activas y no eliminadas;
- fecha de nacimiento no futura (solo HTTP);
- código de unidad se normaliza y valida por regex (solo HTTP);
- `active` puede omitirse, coherente con DEFAULT true, pero el modelo no refleja el default antes de responder.

### Noticias

Index valida search 150, enum y per_page. Store/Update validan título/subtítulo 255, textos, `content` array con `type=doc`, estado enum y fecha requerida cuando el status enviado es published. Trash normaliza búsqueda y limita per_page.

```json
{"type":"doc","content":[{"type":"paragraph","content":[]}]}
```

Solo se valida la raíz TipTap. Una noticia ya publicada puede recibir únicamente `published_at:null`: `required_if` no ve status en la petición y DB también permite null.

### Multimedia

- 1–20 imágenes JPG/JPEG/PNG/WebP, máximo 10.240 KB por archivo, alt 255, caption 1.000;
- video: URL 2.048 y título 255;
- reorder: IDs y posiciones integer/distinct; el controlador exige conjunto completo y `0..N-1`;
- reglas coinciden con longitudes y CHECK/UNIQUE; caption HTTP es más restrictivo que `text`.

## 10. Resources y JSON

| Resource | Campos principales |
|---|---|
| `UserResource` | id, email, personal anidado, roles, permisos, timestamps |
| `OrganizationalUnitResource` | id, name, code, description, active, timestamps |
| `PositionResource` / `ProfessionResource` | catálogo y timestamps |
| `StaffMemberResource` | datos snake_case, fecha Y-m-d, catálogos/usuario anidados |
| `NewsResource` | contenido, `publishedAt`, estado, multimedia, `createdBy/updatedBy`, timestamps ISO |
| `NewsImageResource` | id, UUID `filename`, `baseUrl`, alt, caption, position |
| `NewsVideoResource` | id, `youtubeUrl`, title, position |

Paginación usa `data`, `links`, `meta`. People/AccessControl usa snake_case y Communication mezcla camelCase.

## 11. Servicios y módulos

`UserService` crea usuario/rol en transacción, actualiza, reemplaza rol y carga relaciones. `NewsTrashService` restaura, fuerza eliminación, deja CASCADE a PostgreSQL y limpia archivos después del commit. `ImageService` genera UUID, decodifica, resize, produce tres variantes, almacena, elimina y protege el directorio contra traversal.

### Noticias

- estados protegidos por enum y CHECK;
- slug con `Str::slug`, fallback `noticia`, sufijos; consulta `withTrashed`;
- editar título no cambia slug;
- publicar/cambiar desde o hacia publicado exige `news.publish`;
- auditoría `created_by/updated_by` en CRUD, multimedia y papelera;
- listado filtra/busca con `ILIKE`; no existe lectura pública;
- soft delete conserva contenido y multimedia.

### Imágenes

| Opción | Valor |
|---|---|
| Directorio/disco | `communication/news`; `MEDIA_DISK`, public por defecto |
| Resize | scaleDown a máximo 1.920 px de ancho |
| Formatos/calidad | WebP 80, PNG, JPEG 82 progresivo |
| Procesamiento | orientación EXIF, sin animación, strip de metadatos |

El DB guarda UUID sin extensión. La carga limpia archivos parciales ante rollback. Delete normaliza posiciones y luego borra variantes.

Riesgo: discos con `throw=false` y `ImageService::delete()` ignora el booleano de `Storage::delete()`. Un false no entra al catch y puede dejar huérfanos aunque la API informe éxito o `media_cleanup_pending=false`.

### Videos

Alta asigna `max(position)+1`; update conserva posición; reorder usa posiciones temporales; delete normaliza.

## 12. Papelera

```text
DELETE normal → Soft Delete → conserva filas multimedia y archivos
restore       → quita deleted_at → conserva status/published_at → actualiza updated_by
force delete  → borra news → CASCADE filas → intenta borrar WebP/PNG/JPEG
```

`comunicador` no recibe permisos de papelera. Esos permisos existen, pero solo superadmin accede en la siembra estándar.

## 13. Seeders y factories

1. `RolesAndPermissionsSeeder`: 45 permisos, cuatro roles y matrices.
2. `SuperAdminSeeder`: usa configuración sensible, crea/reutiliza cuenta/rol.
3. `NewsPermissionsSeeder`: ocho permisos, crea comunicador y asigna cinco.

`SuperAdminSeeder` usa `firstOrCreate`: cambiar password configurado no actualiza una cuenta existente; un superadmin soft-deleted con el mismo email puede chocar con UNIQUE.

Solo existe `UserFactory`. No hay factories de People/noticias/multimedia. `DatabaseSeeder` conserva código comentado e imports del esqueleto.

## 14. Pruebas

Pest aplica `RefreshDatabase` a Feature. `phpunit.xml` fija `APP_ENV=testing`; `.env.testing` usa PostgreSQL y una base distinta de desarrollo. Caché/sesión array, cola sync y mail array. No se exponen credenciales.

| Archivo | Tests | Cobertura |
|---|---:|---|
| Status | 1 | API |
| Auth | 8 | login, me, logout, blacklist, refresh vigente |
| Authorization | 9 | catálogos, bypass, comunicador |
| User | 11 | usuarios, password, rol, soft delete |
| StaffMember | 13 | autorización, CI, FK activas, soft delete |
| News | 17 | CRUD, slug, TipTap, publicación |
| NewsImage | 10 | carga, validación, reorder, delete |
| NewsVideo | 11 | alta, validación, reorder, delete |
| NewsTrash | 12 | permisos, restore, CASCADE, force delete |

Ejecución 30/08/2026: **92 passed, 361 assertions, 31.217 s**.

Sin cobertura: CRUD HTTP de los tres catálogos; login de personal inactivo; edición/degradación de superadmin; refresh expirado/429; fallos físicos reales; concurrencia; default active omitido; unit tests de servicios/DTO; rendimiento/N+1.

## 15. Configuración operativa

| Área | Estado |
|---|---|
| DB | PostgreSQL; usa `ILIKE`, jsonb, regex, CHECK, COALESCE |
| Auth | JWT guard api; Sanctum ausente |
| Errores | JSON en `api/*` |
| Media | public por defecto; S3 configurable |
| Imagen | GD por defecto mediante `intervention-image.php` |
| Secretos | APP, DB, JWT, superadmin y externos; valores omitidos |
| Rutas no API | `/`, `/up`; Storage según configuración |

`.env.example` no incluye variables JWT aunque `JWT_SECRET` es imprescindible. `config/images.php` no tiene referencias y duplica parcialmente `config/intervention-image.php`.

## 16. Hallazgos técnicos

### Crítico

1. **Secretos versionados.** `.env` y `.env.testing` están en el índice Git y `.gitignore` no los excluye. Incluyen categorías sensibles APP, PostgreSQL, JWT y superadmin. No se copiaron valores.

### Alto

1. **Protección incompleta de superadmin:** `users.update` permite cambiar sus credenciales y `roles.assign` degradarlo; los controles actuales solo cubren asignación del rol y DELETE.
2. **Inactivar/eliminar personal no revoca login** del usuario asociado.
3. **Limpieza física silenciosa:** delete puede retornar false sin excepción, dejando archivos huérfanos no reportados.

### Medio

1. Refresh posterior a expiración no llega al controlador por `auth:api`.
2. PATCH permite noticia published con fecha null.
3. DB guarda default active=true, pero el Resource inmediato puede devolver null si se omitió.
4. Tabla local residual `personal_access_tokens`, ausente del esquema actual.
5. Slug y `max(position)+1` sin lock/retry pueden chocar bajo concurrencia.
6. `ILIKE '%texto%'` sin índices trigram; FK de personal sin índices explícitos.
7. Creaciones People/usuarios responden 201; noticias/imágenes/videos responden 200.

### Bajo

1. Comunicador/noticias viven fuera de los enums de rol/permisos.
2. Permisos de módulos inexistentes, config de imagen sin uso, comentarios/imports residuales y estilos inconsistentes.
3. JSON mezcla snake_case y camelCase.

### Correcto

- rutas sin duplicados ni métodos faltantes;
- todos los permisos usados se siembran;
- Requests coinciden en longitudes/regex principales con DB;
- relaciones principales se cargan anticipadamente;
- scope binding protege multimedia anidada;
- transacciones en operaciones críticas;
- slug considera eliminadas;
- soft delete conserva multimedia y force delete usa CASCADE;
- suite PostgreSQL aislada completamente aprobada.

## 17. Estado técnico actual

### Correcto

Arquitectura comprensible; JWT/Spatie operativos; constraints PostgreSQL; CRUD principal; rollback de altas multimedia; papelera; 92 tests verdes.

### Mejorable

HTTP/JSON uniforme, filtros validados, defaults de modelo, cobertura, índices y limpieza residual.

### Pendiente

Módulos geográficos/asistencias hoy solo permisos; API pública de noticias; estrategia de restore para otras entidades; retiro reproducible de tabla Sanctum.

### Riesgos

Exposición de secretos, escalamiento mediante superadmin, acceso de personal inactivo, huérfanos y carreras.

### Recomendaciones prioritarias

1. Retirar `.env`/`.env.testing` del índice e historial, corregir `.gitignore` y rotar APP, DB, JWT y credenciales administrativas.
2. Crear política integral de superadmin: credenciales, rol, último superadmin y auto-degradación.
3. Definir y aplicar revocación de cuenta al desactivar/eliminar personal.
4. Comprobar el retorno de delete o activar excepciones; persistir/reintentar cleanup pendiente.
5. Rediseñar refresh seguro para tokens expirados dentro de `refresh_ttl` y probarlo.
6. Garantizar `published ⇒ published_at no null` en Request y CHECK.
7. Reflejar defaults active o refrescar antes de responder; probarlo.
8. Eliminar `personal_access_tokens` mediante migración explícita, sin editar migraciones desplegadas.
9. Añadir retry/lock para slug/posiciones y mapear UNIQUE a 409/422.
10. Cubrir catálogos, seguridad privilegiada, fallos físicos, throttle y consultas críticas.

## 18. Validación final

- 50 endpoints contrastados: 0 duplicados, 0 acciones faltantes.
- 13 migraciones contrastadas con modelos/Requests; todas aplicadas localmente.
- 53 permisos contrastados; ninguno usado falta.
- 9 archivos, 92 tests ejecutados solo en PostgreSQL testing separado.
- `.gitignore` revisado; env versionados reportados sin revelar valores.
- No se modificó funcionalidad de negocio.

### Limitaciones

Laravel Boost no estuvo disponible como MCP; se usaron Artisan, configuración, código y base local en lectura. No hubo carga, simulación concurrente ni inspección de infraestructura productiva externa.

</details>
