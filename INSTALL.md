# Sistema Specter v2 — Módulo de Compras
## Instalación

### Requisitos
- PHP 8.1+
- PostgreSQL 17
- Composer
- Laragon (recomendado) o cualquier servidor PHP local

---

### 1. Copiar archivos

Copia la carpeta `specter_v2/` a tu directorio web:

```
C:\laragon\www\specter\
```

---

### 2. Instalar dependencias PHP (Dompdf)

Abre una terminal en la carpeta del proyecto:

```bash
cd C:\laragon\www\specter
composer install
```

Esto descarga Dompdf en la carpeta `vendor/`. Si no tienes Composer instalado:
- Descárgalo desde https://getcomposer.org
- O instala Laragon Full que lo incluye

> **Sin Composer:** el sistema funciona igual. El PDF del comprobante se
> servirá como HTML en vez de PDF hasta que `vendor/autoload.php` exista.

---

### 3. Ejecutar el schema SQL

En pgAdmin 4, abre la Query Tool conectado a tu BD y ejecuta en orden:

**Paso A — Schema base (si es instalación nueva):**
```sql
-- Pegar el contenido de specter_db.sql
```

**Paso B — Migración v2 (siempre ejecutar):**
```sql
-- Pegar el contenido de specter_db_v2.sql
```

O desde psql:
```bash
psql -U postgres -d specter_db -f specter_db.sql
psql -U postgres -d specter_db -f specter_db_v2.sql
```

---

### 4. Configurar la conexión

Edita `includes/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'specter_db');
define('DB_USER', 'postgres');
define('DB_PASS', 'tu_password');  // ← cambia esto
```

---

### 5. Abrir en el navegador

```
http://localhost/specter/
```

**Cuentas de prueba** (password: `specter123`):
| Correo | Rol |
|--------|-----|
| jorge@specter.com | Jugador |
| admin@specter.com | Moderador |

---

## Nuevas rutas del módulo de compras

| Ruta | Descripción |
|------|-------------|
| `/specter/checkout.php` | Stepper de compra (resumen → tarjeta → confirmación) |
| `/specter/wallet.php` | Cartera Specter (saldo + historial) |
| `/specter/canjear.php` | Canjear código XXXX-XXXX-XXXX |
| `/specter/tarjetas_specter.php` | Catálogo de tarjetas ($100, $250, $500) |
| `/specter/api/pago.php` | API: validar tarjeta (Luhn), procesar pago |
| `/specter/api/wallet.php` | API: saldo, movimientos, recargar |
| `/specter/api/codigo.php` | API: canjear código, comprar tarjeta |
| `/specter/api/comprobante_pdf.php?id=N` | Descargar comprobante PDF |

---

## Flujo de pago

```
carrito.php
    └── [Procesar pago] → checkout.php
            ├── Paso 1: Resumen del pedido
            ├── Paso 2: Método de pago
            │       ├── Solo saldo Specter
            │       ├── Solo tarjeta (validación Luhn)
            │       └── Mixto (saldo + tarjeta)
            └── Paso 3: Confirmación + link PDF
```

## Flujo de tarjetas Specter

```
tarjetas_specter.php → [Comprar $100/$250/$500]
    └── api/codigo.php?action=comprar_tarjeta
            └── Genera código XXXX-XXXX-XXXX

canjear.php → [Ingresar código]
    └── api/codigo.php?action=canjear
            └── Acredita saldo en wallet del jugador
```

---

## Notas sobre seguridad

- **Luhn:** Solo valida el formato matemático de la tarjeta. No hay cargo real.
- **Números de tarjeta:** Nunca se almacenan. Solo los últimos 4 dígitos + marca.
- **CVV:** Solo se captura en el formulario para UX; se descarta en el servidor.
- **CSRF:** Todos los endpoints POST validan el token de sesión.
- **Códigos:** Formato alfanumérico sin ambigüedades (sin 0/O/1/I), 12 chars en 3 grupos.
