# List - Guia de instalacion y ejecucion

Este documento explica como instalar y correr el proyecto `List` en otro ordenador desde cero.

## 1. Requisitos

Instala estas herramientas antes de empezar:

- PHP `8.3` o superior
- Composer `2.x`
- Node.js `20+` (recomendado `22 LTS`)
- npm `10+`
- Git

Extensiones de PHP necesarias (minimo):

- `pdo_sqlite`
- `sqlite3`
- `mbstring`
- `openssl`
- `fileinfo`
- `tokenizer`
- `xml`
- `ctype`
- `json`

## 2. Clonar el repositorio

```bash
git clone https://github.com/datbiggie/List.git
cd List
```

## 3. Instalacion rapida (recomendada)

El proyecto ya incluye un script que instala dependencias, crea `.env`, genera `APP_KEY`, corre migraciones y construye frontend.

```bash
composer run setup
```

Si ese comando termina sin errores, puedes ir directo a la seccion **7. Ejecutar el proyecto**.

## 4. Instalacion manual paso a paso

Usa esta opcion si prefieres controlar cada paso o depurar errores.

### 4.1 Instalar dependencias PHP

```bash
composer install
```

### 4.2 Crear archivo de entorno

```bash
cp .env.example .env
```

En Windows PowerShell, si `cp` no funciona:

```powershell
Copy-Item .env.example .env
```

### 4.3 Generar clave de Laravel

```bash
php artisan key:generate
```

### 4.4 Crear base de datos SQLite

Crea el archivo fisico de SQLite:

```bash
mkdir -p database
touch database/database.sqlite
```

En Windows PowerShell:

```powershell
if (!(Test-Path database)) { New-Item -ItemType Directory database | Out-Null }
if (!(Test-Path database/database.sqlite)) { New-Item -ItemType File database/database.sqlite | Out-Null }
```

### 4.5 Verificar configuracion de `.env` para SQLite

Asegurate de tener estas variables (o equivalentes):

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Nota: El proyecto ya usa SQLite por defecto en `config/database.php`, pero es buena practica confirmarlo en `.env`.

### 4.6 Ejecutar migraciones

```bash
php artisan migrate
```

### 4.7 Instalar dependencias frontend

```bash
npm install
```

## 5. Ajustes recomendados de PHP (`php.ini`)

Para carga de archivos grandes y procesos largos:

```ini
upload_max_filesize = 50M
post_max_size = 55M
memory_limit = 512M
max_execution_time = 300
```

Luego reinicia tu terminal o servicio PHP.

## 6. Comandos utiles del proyecto

- Desarrollo completo (servidor + cola + logs + vite):

```bash
composer run dev
```

- Solo backend Laravel:

```bash
php artisan serve
```

- Solo frontend Vite:

```bash
npm run dev
```

- Construccion de assets para produccion:

```bash
npm run build
```

- Ejecutar pruebas:

```bash
composer test
```

## 7. Ejecutar el proyecto

Opcion simple:

```bash
composer run dev
```

Luego abre en tu navegador la URL que muestre `php artisan serve` (normalmente `http://127.0.0.1:8000`).

## 8. Flujo para instalar en otra PC (resumen rapido)

```bash
git clone https://github.com/datbiggie/List.git
cd List
composer run setup
composer run dev
```

## 9. Problemas comunes

- Error de SQLite (`could not find driver`):
    - Activa `pdo_sqlite` y `sqlite3` en `php.ini`.
- Error con `APP_KEY`:
    - Ejecuta `php artisan key:generate`.
- Error de tablas inexistentes:
    - Ejecuta `php artisan migrate`.
- Error de frontend:
    - Ejecuta `npm install` y luego `npm run dev`.

## 10. Produccion (referencia minima)

Para un despliegue basico:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm install
npm run build
php artisan optimize
```

Si necesitas, puedo generar una segunda version de este README orientada 100% a Windows (PowerShell) o una orientada a Linux (Ubuntu/Debian).
