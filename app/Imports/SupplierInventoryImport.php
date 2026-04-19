<?php

namespace App\Imports;

use App\Models\TempSupplierInventory;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use RuntimeException;

class SupplierInventoryImport implements ToModel, WithHeadingRow, WithChunkReading
{
    protected const CODE_KEYS = ['codigo', 'código', 'code', 'sku'];
    protected const DESCRIPTION_KEYS = ['descripcion', 'descripción', 'description', 'detalle'];
    protected const BRAND_KEYS = ['marca', 'brand', 'fabricante', 'marca_producto', 'marca del producto'];
    protected const QUANTITY_KEYS = ['cant', 'stock', 'cantidad', 'existencia', 'inventario'];

    public function model(array $row)
    {
        // Validación de cabeceras esperadas (sin usar dd)
        if (!$this->rowHasAnyKey($row, self::CODE_KEYS)) {
            throw new RuntimeException(
                'No se encontró la columna "Código" en el inventario proveedor. Cabeceras detectadas: ' . implode(', ', array_keys($row))
            );
        }

        $codigo = $this->normalizeCode($this->rowValue($row, self::CODE_KEYS));
        $descripcion = trim((string) ($this->rowValue($row, self::DESCRIPTION_KEYS) ?? ''));
        $marca = trim((string) ($this->rowValue($row, self::BRAND_KEYS) ?? ''));

        if ($marca === '') {
            $marca = trim((string) ($this->fallbackBrandFromUnnamedColumns($row) ?? ''));
        }

        $cantidad = (int) ($this->rowValue($row, self::QUANTITY_KEYS) ?? 0);

        // Ignorar filas vacías, títulos de sección y filas de cabecera repetida
        if ($codigo === '' || $this->isHeaderLikeRow($row, $codigo, $descripcion)) {
            return null;
        }

        return new TempSupplierInventory([
            'code'        => $codigo,
            'description' => $descripcion !== '' ? $descripcion : null,
            'brand'       => $marca !== '' ? $marca : null,
            'quantity'    => $cantidad,
        ]);
    }

    protected function isHeaderLikeRow(array $row, string $codigo, string $descripcion): bool
    {
        $normalize = static fn (string $text): string => mb_strtoupper(trim($text), 'UTF-8');

        $codigoNorm = $normalize($codigo);
        $descripcionNorm = $normalize($descripcion);
        $rowValuesNorm = $normalize(implode(' ', array_filter(array_map(
            static fn ($value) => is_scalar($value) || $value === null ? (string) $value : '',
            $row
        ))));

        $codeHeaders = ['CODIGO', 'CÓDIGO', 'CODE'];
        $descHeaders = ['DESCRIPCION', 'DESCRIPCIÓN', 'DESCRIPTION'];
        $blockedLabels = ['LISTA DE PRECIOS', 'PRICE LIST'];

        return in_array($codigoNorm, $codeHeaders, true)
            || (in_array($codigoNorm, $codeHeaders, true) && in_array($descripcionNorm, $descHeaders, true))
            || ($codigoNorm === 'CODIGO' && $descripcionNorm === 'DESCRIPCION')
            || in_array($codigoNorm, $blockedLabels, true)
            || in_array($descripcionNorm, $blockedLabels, true)
            || collect($blockedLabels)->contains(fn (string $label) => str_contains($rowValuesNorm, $label));
    }

    protected function normalizeCode(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        // Excel suele convertir "001" a 1 (int/float). Aquí recuperamos al menos 3 dígitos.
        if (is_int($value) || is_float($value)) {
            $numericCode = (string) (int) round((float) $value);

            return str_pad($numericCode, 3, '0', STR_PAD_LEFT);
        }

        return trim((string) $value);
    }

    protected function rowHasAnyKey(array $row, array $candidateKeys): bool
    {
        $normalizedRowKeys = [];

        foreach (array_keys($row) as $key) {
            $normalizedRowKeys[] = $this->normalizeHeaderKey((string) $key);
        }

        foreach ($candidateKeys as $candidateKey) {
            if (array_key_exists($candidateKey, $row)) {
                return true;
            }

            if (in_array($this->normalizeHeaderKey($candidateKey), $normalizedRowKeys, true)) {
                return true;
            }
        }

        return false;
    }

    protected function rowValue(array $row, array $candidateKeys): mixed
    {
        foreach ($candidateKeys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        $normalizedRow = [];

        foreach ($row as $key => $value) {
            $normalizedRow[$this->normalizeHeaderKey((string) $key)] = $value;
        }

        foreach ($candidateKeys as $candidateKey) {
            $normalizedKey = $this->normalizeHeaderKey($candidateKey);

            if (array_key_exists($normalizedKey, $normalizedRow)) {
                return $normalizedRow[$normalizedKey];
            }
        }

        return null;
    }

    protected function normalizeHeaderKey(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^A-Z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    protected function fallbackBrandFromUnnamedColumns(array $row): ?string
    {
        foreach ($row as $key => $value) {
            $isUnnamedColumn = is_int($key) || ctype_digit((string) $key);
            if (!$isUnnamedColumn) {
                continue;
            }

            $candidate = trim((string) ($value ?? ''));
            if ($candidate === '') {
                continue;
            }

            // Evitamos tomar columnas numéricas/monetarias; marca suele contener letras.
            if (!preg_match('/[A-Z]/i', $candidate)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}