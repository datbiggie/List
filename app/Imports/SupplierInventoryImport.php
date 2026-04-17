<?php

namespace App\Imports;

use App\Models\TempSupplierInventory;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use RuntimeException;

class SupplierInventoryImport implements ToModel, WithHeadingRow, WithChunkReading
{
    public function model(array $row)
    {
        // Validación de cabeceras esperadas (sin usar dd)
        if (!array_key_exists('codigo', $row) && !array_key_exists('código', $row)) {
            throw new RuntimeException(
                'No se encontró la columna "Código" en el inventario proveedor. Cabeceras detectadas: ' . implode(', ', array_keys($row))
            );
        }

        $codigo = $this->normalizeCode($row['codigo'] ?? $row['código'] ?? null);
        $descripcion = trim((string) ($row['descripcion'] ?? $row['descripción'] ?? ''));
        $marca = trim((string) ($row['marca'] ?? ''));
        $cantidad = (int) ($row['cant'] ?? $row['stock'] ?? $row['cantidad'] ?? $row['existencia'] ?? 0);

        // Ignorar filas vacías y filas de cabecera repetida
        if ($codigo === '' || $this->isHeaderLikeRow($codigo, $descripcion)) {
            return null;
        }

        return new TempSupplierInventory([
            'code'        => $codigo,
            'description' => $descripcion !== '' ? $descripcion : null,
            'brand'       => $marca !== '' ? $marca : null,
            'quantity'    => $cantidad,
        ]);
    }

    protected function isHeaderLikeRow(string $codigo, string $descripcion): bool
    {
        $normalize = static fn (string $text): string => mb_strtoupper(trim($text), 'UTF-8');

        $codigoNorm = $normalize($codigo);
        $descripcionNorm = $normalize($descripcion);

        $codeHeaders = ['CODIGO', 'CÓDIGO', 'CODE'];
        $descHeaders = ['DESCRIPCION', 'DESCRIPCIÓN', 'DESCRIPTION'];

        return in_array($codigoNorm, $codeHeaders, true)
            || (in_array($codigoNorm, $codeHeaders, true) && in_array($descripcionNorm, $descHeaders, true))
            || ($codigoNorm === 'CODIGO' && $descripcionNorm === 'DESCRIPCION');
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