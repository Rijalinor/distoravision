<?php

namespace App\Exports\Sheets;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

trait ExcelStyler
{
    // ─────── Palette ────────────────────────────
    protected string $clrNavy    = 'FF0D1B2A';  // title bg
    protected string $clrBlue    = 'FF1B3A5C';  // header bg
    protected string $clrGold    = 'FFFBBF24';  // accent text
    protected string $clrWhite   = 'FFFFFFFF';
    protected string $clrRowOdd  = 'FFF8FAFC';
    protected string $clrRowEven = 'FFEFF6FF';
    protected string $clrRed     = 'FFFEE2E2';
    protected string $clrGreen   = 'FFF0FDF4';
    protected string $clrTotal   = 'FFE0F2FE';
    protected string $clrBorder  = 'FFCBD5E1';
    protected string $clrMuted   = 'FF64748B';

    /** Style wrapper: Title row (row 1) */
    protected function styleTitle(Worksheet $sheet, string $range, string $text = ''): void
    {
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 16, 'color' => ['argb' => $this->clrWhite], 'name' => 'Calibri'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $this->clrNavy]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
        ]);
    }

    /** Style subtitle row */
    protected function styleSubtitle(Worksheet $sheet, string $range): void
    {
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FFB0BEC5'], 'name' => 'Calibri'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $this->clrNavy]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    /** Style a section header (section label like "A. KPI UTAMA") */
    protected function styleSectionHeader(Worksheet $sheet, string $range): void
    {
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => $this->clrGold], 'name' => 'Calibri'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $this->clrBlue]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
    }

    /** Style column header row (the bold row with column names) */
    protected function styleColHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $this->clrWhite], 'name' => 'Calibri'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $this->clrBlue]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF1E3A5F']]],
        ]);
    }

    /** Alternate row colours in a data range */
    protected function styleDataRows(Worksheet $sheet, int $startRow, int $endRow, string $lastCol): void
    {
        for ($row = $startRow; $row <= $endRow; $row++) {
            $range = "A{$row}:{$lastCol}{$row}";
            $bg    = ($row % 2 === 0) ? $this->clrRowEven : $this->clrRowOdd;
            $sheet->getStyle($range)->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $this->clrBorder]],
                ],
                'font'    => ['size' => 10, 'name' => 'Calibri'],
            ]);
        }
    }

    /** Style a totals row */
    protected function styleTotalsRow(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'    => ['bold' => true, 'size' => 10, 'name' => 'Calibri'],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $this->clrTotal]],
            'borders' => ['topBorder' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => $this->clrBlue]]],
        ]);
    }

    /** Apply Indonesian currency number format to a column range */
    protected function formatCurrencyCol(Worksheet $sheet, string $colRange): void
    {
        $sheet->getStyle($colRange)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle($colRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    /** Apply percent number format */
    protected function formatPercentCol(Worksheet $sheet, string $colRange): void
    {
        $sheet->getStyle($colRange)->getNumberFormat()->setFormatCode('0.00"%"');
        $sheet->getStyle($colRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    /** Row height helper */
    protected function setRowHeight(Worksheet $sheet, int $row, float $height): void
    {
        $sheet->getRowDimension($row)->setRowHeight($height);
    }

    /** Outer box border around a range */
    protected function outerBorder(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => $this->clrBlue]]],
        ]);
    }
}
