<?php

namespace App\Imports;

use App\Models\TempLocalInventory;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use RuntimeException;

class LocalInventoryImport implements ToModel, WithHeadingRow, WithChunkReading
{
    /**
     * Le decimos a Laravel Excel exactamente en qué fila están las cabeceras.
     * Si en tu Excel están en la fila 3 o 4, cambia este número.
     */
    public function headingRow(): int
    {
        return 2; 
    }

    public function model(array $row)
    {
        // Validación de cabeceras esperadas (sin frenar el servidor con dd)
        if (!array_key_exists('codigo', $row) && !array_key_exists('codigo', $row)) {
            throw new RuntimeException(
                'No se encontró la columna "Código" en el inventario local. Cabeceras detectadas: ' . implode(', ', array_keys($row))
            );
        }

        $codigo = $this->normalizeCode($row['codigo'] ?? $row['código'] ?? null);
        $descripcion = trim((string) ($row['descripción'] ?? $row['descripcion'] ?? ''));
        $marca = trim((string) ($row['marca'] ?? ''));

        // Ignorar filas vacías, títulos de sección y filas que son cabeceras repetidas dentro del archivo
        if ($codigo === '' || $this->isHeaderLikeRow($row, $codigo, $descripcion)) {
            return null;
        }

        return new TempLocalInventory([
            'code'        => $codigo,
            'description' => $descripcion !== '' ? $descripcion : null,
            'brand'       => $marca !== '' ? $marca : null,
            'is_resolved' => false,
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

    public function chunkSize(): int
    {
        return 1000;
    }
}