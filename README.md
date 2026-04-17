import os

# Definición del contenido del README basado en la arquitectura desarrollada
readme_content = """# InventorySync | Reconciliación de Inventario B2B

## 📌 Visión General
InventorySync es una aplicación de escritorio de alto rendimiento construida con **Laravel Native** diseñada para automatizar la conciliación de inventarios locales frente a listas de precios de proveedores. El sistema maneja volúmenes de datos de +6,000 registros, aplicando algoritmos de búsqueda difusa (*Fuzzy Matching*) y una base de conocimiento persistente para reducir la carga de trabajo manual.

## 🛠 Stack Tecnológico (TALL Stack + Native)
- **Framework:** Laravel 11+
- **Frontend:** Livewire 3 (Componentes reactivos)
- **Estilos:** Tailwind CSS (Sistema de diseño inspirado en Mintlify)
- **Base de Datos:** SQLite (Local y embebida)
- **Motor de Búsqueda:** TNTSearch (Full-Text Search nativo)
- **Procesamiento de Datos:** Laravel Excel (Maatwebsite/Excel) con Chunk Reading
- **Runtime:** Laravel Native (PHP 8.2+)

## 🏗 Arquitectura y Principios
El proyecto sigue estrictamente los principios **SOLID**, **DRY** y **KISS**:

### 1. Inversión de Dependencias (DIP)
Se utilizan contratos (Interfaces) para desacoplar la lógica de negocio de las implementaciones concretas:
- `InventoryParserInterface`: Define el comportamiento para la ingesta de archivos Excel.
- `ProductMatcherInterface`: Define el motor de búsqueda y comparación de productos.

### 2. Arquitectura Orientada a Eventos
Para mantener el desacoplamiento, el sistema utiliza eventos de Laravel:
- `SupplierInventoryImported`: Disparado tras la carga del Excel del proveedor.
- **Listener:** `BuildSearchIndex` se encarga de regenerar el índice de TNTSearch de forma aislada.

### 3. Capa de Servicios
Toda la lógica compleja reside en `app/Services`, manteniendo los componentes de Livewire limpios y enfocados únicamente en la gestión del estado de la interfaz.

## 📂 Estructura de Datos (Schema)
El sistema utiliza tablas temporales para garantizar la integridad de los datos maestros:
- `temp_local_inventories`: Almacena el inventario cargado por el usuario.
- `temp_supplier_inventories`: Almacena el catálogo del proveedor para comparación.
- `alias_dictionaries`: **Base de conocimiento**. Almacena los vínculos manuales aprobados por el usuario para auto-conciliaciones futuras.

## 🚀 Flujo de Trabajo
1. **Ingesta:** Carga masiva mediante `WithChunkReading` para optimizar el uso de memoria RAM.
2. **Auto-Match:** Ejecución de queries SQL nativos para resolver coincidencias exactas y alias conocidos en milisegundos.
3. **Fuzzy Search:** Indexación mediante TNTSearch para encontrar coincidencias difusas (Levenshtein Distance) en productos no resueltos.
4. **Human-in-the-loop:** Interfaz visual para que el usuario valide o descarte sugerencias de alta confianza.
5. **Resultado:** Visualización de stock actualizado y exportación.

## 🎨 Sistema de Diseño
Inspirado en **Mintlify**:
- Fondo: `#ffffff` (Blanco puro)
- Acentos: `#18E299` (Brand Green)
- Tipografía: `Inter` (UI) y `Geist Mono` (Datos técnicos/Códigos)
- Bordes: `rgba(0,0,0,0.05)` (5% opacidad para separación sutil)
- Radios: `16px` para tarjetas y `9999px` (Pill) para botones/inputs.

## ⚙️ Configuración del Entorno
Para el correcto funcionamiento en desarrollo, asegúrese de tener los siguientes límites en su `php.ini`:
```ini
upload_max_filesize = 50M
post_max_size = 55M
memory_limit = 512M
max_execution_time = 300