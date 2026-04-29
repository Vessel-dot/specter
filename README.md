# ◇ Specter — Sistema de Videojuegos

Plataforma web de gestión y venta de videojuegos digitales. Permite a los jugadores explorar un catálogo, comprar juegos, gestionar su biblioteca personal, escribir reseñas y administrar su cartera virtual. Incluye un panel de moderación para la gestión de usuarios y contenido.

---

## 🛠 Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| Servidor web | Apache 2.4 |
| Backend | PHP 8.2 |
| Base de datos | PostgreSQL 15 |
| Contenedorización | Docker + Docker Compose |
| Frontend | HTML5, CSS3, JavaScript (Vanilla) |
| PDF | Dompdf |

---

## 📁 Estructura del Proyecto

```
specter/
├── backend/
│   ├── config/
│   │   └── config.php              # Conexión PDO a PostgreSQL, CSRF, helpers BD
│   ├── controllers/
│   │   ├── carrito.php             # API: agregar/quitar ítems del carrito
│   │   ├── catalogo.php            # API: detalle de juego, toggle carrito
│   │   ├── codigo.php              # API: generar y canjear códigos de recarga
│   │   ├── comprobante_pdf.php     # API: generar comprobante PDF de compra
│   │   ├── moderador.php           # API: aprobar/bloquear/revisar reseñas
│   │   ├── pago.php                # API: procesar pagos (tarjeta/saldo/mixto)
│   │   ├── resenas.php             # API: crear y eliminar reseñas
│   │   └── wallet.php              # API: recargar saldo con tarjeta
│   └── helpers/
│       ├── auth_guard.php          # Protección de rutas y validación de sesión
│       ├── bundle.php              # Lógica de descuento por bundle
│       ├── carrito_helper.php      # Helper para obtener/crear carrito
│       ├── footer.php              # Plantilla de pie de página
│       ├── header.php              # Plantilla de cabecera (jugador)
│       ├── header_mod.php          # Plantilla de cabecera (moderador)
│       ├── luhn.php                # Validación de tarjetas (algoritmo Luhn)
│       └── pdf_helper.php          # Generador HTML para comprobantes PDF
│
├── frontend/
│   └── src/
│       └── pages/
│           ├── index.php           # Dashboard principal
│           ├── catalogo.php        # Catálogo de videojuegos
│           ├── biblioteca.php      # Biblioteca personal del jugador
│           ├── carrito.php         # Carrito de compras
│           ├── checkout.php        # Proceso de pago (stepper)
│           ├── resenas.php         # Escribir y ver reseñas
│           ├── wallet.php          # Cartera virtual y saldo
│           ├── tarjetas_specter.php# Gestión de tarjetas guardadas
│           ├── canjear.php         # Canjear códigos de recarga
│           ├── login.php           # Inicio de sesión
│           ├── registro.php        # Crear cuenta
│           ├── logout.php          # Cerrar sesión
│           └── moderador/
│               ├── dashboard.php   # Panel principal de moderación
│               ├── usuarios.php    # Gestión de usuarios y roles
│               ├── resenas.php     # Moderación de reseñas
│               └── reportes.php    # Reportes y estadísticas
│
├── database/
│   ├── specter_db.sql              # Schema principal y datos iniciales
│   └── specter_db_v2.sql          # Migración v2 (saldo, tarjetas, movimientos)
│
├── assets/
│   ├── css/
│   │   └── style.css              # Estilos globales (3 temas: dark/light/neon)
│   └── js/
│       └── app.js                 # Lógica JS compartida (modal, carrito, slider)
│
├── Dockerfile                     # Imagen PHP 8.2 + Apache + extensiones PostgreSQL
├── docker-compose.yml             # Orquestación: app (puerto 8080) + db (puerto 5432)
└── composer.json                  # Dependencias PHP (Dompdf)
```

---

## ⚙️ Requisitos Previos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado y corriendo
- Navegador web (Chrome recomendado)
- PowerShell (Windows) o Terminal (Mac/Linux)

---

## 🚀 Instalación y Puesta en Marcha

### 1. Clonar o descargar el proyecto

```bash
# Colocar la carpeta en C:\specter (Windows) o ~/specter (Mac/Linux)
```

### 2. Levantar los contenedores

```powershell
cd C:\specter
docker compose up -d
```

> La primera vez descarga las imágenes de PHP y PostgreSQL (~500MB). Las siguientes veces es instantáneo.

### 3. Importar la base de datos

```powershell
# Schema principal (tablas y datos base)
Get-Content C:\specter\database\specter_db.sql | docker exec -i specter_db psql -U postgres -d specter_db

# Migración v2 (saldo, tarjetas, movimientos)
Get-Content C:\specter\database\specter_db_v2.sql | docker exec -i specter_db psql -U postgres -d specter_db
```

### 4. Acceder a la aplicación

```
http://localhost:8080/specter/
```

---

## 🔄 Comandos Docker Útiles

```powershell
# Iniciar contenedores
docker compose up -d

# Detener contenedores
docker compose down

# Ver logs en tiempo real
docker logs specter_app -f

# Reconstruir imagen (tras cambios en Dockerfile)
docker compose build --no-cache
docker compose up -d

# Reiniciar solo la app (tras cambios en PHP)
docker restart specter_app
```

---

## 🗄️ Acceso a la Base de Datos

### Desde PowerShell (consulta rápida)

```powershell
docker exec specter_db psql -U postgres -d specter_db -c "SELECT * FROM jugador;"
```

### Modo interactivo (múltiples queries)

```powershell
docker exec -it specter_db psql -U postgres -d specter_db
```

### Desde pgAdmin u otro cliente

| Parámetro | Valor |
|-----------|-------|
| Host | `localhost` |
| Puerto | `5432` |
| Base de datos | `specter_db` |
| Usuario | `postgres` |
| Contraseña | `tu_conraseña` |

### Queries útiles

```sql
-- Ver todos los usuarios
SELECT id_jugador, nombre, correo, rol FROM jugador;

-- Ver juegos por jugador (biblioteca)
SELECT j.nombre, v.titulo, b.estado, b.progreso
FROM biblioteca b
JOIN jugador j ON j.id_jugador = b.id_jugador
JOIN videojuego v ON v.id_videojuego = b.id_videojuego
ORDER BY j.nombre;

-- Ver reseñas por jugador
SELECT j.nombre, v.titulo, r.calificacion, r.estado_moderacion
FROM resena r
JOIN jugador j ON j.id_jugador = r.id_jugador
JOIN videojuego v ON v.id_videojuego = r.id_videojuego;
```

---

## 👤 Roles de Usuario

### 🎮 Jugador
- Explorar el catálogo de videojuegos
- Agregar juegos al carrito y realizar compras
- Gestionar biblioteca personal (estado y progreso)
- Escribir y editar reseñas
- Administrar cartera virtual (saldo Specter)
- Guardar tarjetas de pago
- Generar y canjear códigos de recarga

### 🛡 Moderador
- Acceso al panel de administración
- Aprobar, bloquear o marcar reseñas en revisión
- Gestionar usuarios (cambiar rol, eliminar cuentas)
- Ver reportes y estadísticas del sistema
- Restricción: siempre debe existir al menos un moderador activo

---

## 🛒 Flujo de Compra

```
Catálogo → Agregar al carrito → Checkout → Seleccionar método de pago
→ Confirmar → Comprobante PDF (descarga automática) → Juego en biblioteca
```

### Métodos de pago disponibles
- **Tarjeta de crédito/débito** (validación Luhn)
- **Saldo Specter** (cartera virtual)
- **Mixto** (saldo + tarjeta)

### Descuentos por Bundle
| Juegos en bundle | Descuento |
|-----------------|-----------|
| 2 juegos | 10% |
| 3 juegos | 20% |
| 4 o más | 30% |

---

## 🔐 Seguridad

- Protección **CSRF** en todos los formularios y endpoints POST
- **Hashing** de contraseñas con `password_hash()` (bcrypt)
- Validación de **rol en base de datos** en cada petición (sesión vs BD)
- Sesión destruida automáticamente si la cuenta es eliminada
- Algoritmo **Luhn** para validación de tarjetas
- Solo los últimos 4 dígitos de la tarjeta se almacenan en BD

---

## 🌐 URLs Principales

| URL | Descripción |
|-----|-------------|
| `/specter/` | Dashboard principal |
| `/specter/catalogo.php` | Catálogo de juegos |
| `/specter/carrito.php` | Carrito de compras |
| `/specter/checkout.php` | Proceso de pago |
| `/specter/biblioteca.php` | Biblioteca personal |
| `/specter/resenas.php` | Reseñas |
| `/specter/wallet.php` | Cartera virtual |
| `/specter/login.php` | Iniciar sesión |
| `/specter/registro.php` | Crear cuenta |
| `/specter/moderador/dashboard.php` | Panel de moderación |

---

## 🗺️ Mapeo Apache (Alias)

Apache redirige las URLs a la nueva estructura de carpetas de forma transparente:

| URL | Carpeta real |
|-----|-------------|
| `/specter/api/*` | `backend/controllers/*` |
| `/specter/moderador/*` | `frontend/src/pages/moderador/*` |
| `/specter/assets/*` | `assets/*` |
| `/specter/*` | `frontend/src/pages/*` |

---

## 📝 Variables de Entorno (docker-compose.yml)

```yaml
POSTGRES_USER:     postgres
POSTGRES_PASSWORD: tu_contraseña
POSTGRES_DB:       specter_db
```

---

## 🐛 Solución de Problemas Comunes

**Error 404 al entrar a la página**
→ Verificar que Docker Desktop esté corriendo y ejecutar `docker compose up -d`

**"relation does not exist" en PHP**
→ La BD está vacía. Importar los archivos SQL del paso 3

**No puedo conectar con pgAdmin**
→ Usar exactamente `localhost:5432` con usuario `postgres` y contraseña `vessel`

**Cambios en PHP no se reflejan**
→ Los cambios en PHP son inmediatos (no requiere rebuild). Recargar el navegador con `Ctrl+Shift+R`

**Cambios en Dockerfile no se aplican**
→ Ejecutar `docker compose build --no-cache && docker compose up -d`
