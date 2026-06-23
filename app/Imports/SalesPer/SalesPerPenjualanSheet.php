<?php

namespace App\Imports\SalesPer;

use App\Imports\SalesPerDataImport;
use App\Models\SalesPerTransaction;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/**
 * Sheet "penjualan" — all rows are Invoices (type=I)
 * Headers: DIST_ID(A), SALES_ID(N), SALES_NAME(O), OUTLET_ID(R), OUTLET_NAME(S),
 *          PRINCIPLE_ID(W), PRINCIPLE_NAME(X), ITEM_NO(AB), ITEM_NAME(AC),
 *          QTY(AF), SUBTOTAL(AM), VAT(AN), SO_DATE(J), SO_NO(F), PFI_NO(D)
 */
class SalesPerPenjualanSheet implements ToCollection, WithChunkReading
{
    use ParsesExcelDate;

    protected SalesPerDataImport $parent;

    public function __construct(SalesPerDataImport $parent)
    {
        $this->parent = $parent;
    }

    public function collection(Collection $rows): void
    {
        $log = $this->parent->getImportLog();
        $isFirst = true;

        foreach ($rows as $index => $row) {
            // Skip header row
            if ($isFirst) {
                $isFirst = false;

                continue;
            }

            try {
                $distId = trim((string) ($row[0] ?? ''));
                $salesCode = trim((string) ($row[13] ?? ''));

                // Skip empty rows
                if (empty($distId) && empty($salesCode)) {
                    continue;
                }

                $soDate = $this->parseExcelDate($row[9] ?? null);

                SalesPerTransaction::create([
                    'sales_per_import_log_id' => $log->id,
                    'type' => 'I',
                    'branch_code' => $distId,
                    'sales_code' => $salesCode,
                    'sales_name' => trim((string) ($row[14] ?? '')),
                    'outlet_code' => trim((string) ($row[17] ?? '')),
                    'outlet_name' => trim((string) ($row[18] ?? '')),
                    'principal_code' => trim((string) ($row[22] ?? '')),
                    'principal_name' => trim((string) ($row[23] ?? '')),
                    'item_no' => trim((string) ($row[27] ?? '')),
                    'item_name' => trim((string) ($row[28] ?? '')),
                    'so_no' => trim((string) ($row[5] ?? '')),
                    'pfi_no' => trim((string) ($row[3] ?? '')),
                    'so_date' => $soDate,
                    'qty' => (int) ($row[31] ?? 0),
                    'subtotal' => (float) ($row[38] ?? 0),
                    'vat' => (float) ($row[39] ?? 0),
                    'period' => $log->period,
                ]);

                $this->parent->incrementImported();
            } catch (\Exception $e) {
                $this->parent->incrementFailed();
                $this->parent->addError('Penjualan Row '.($index + 1).': '.$e->getMessage());
            }
        }

        // Update log periodically
        $log->update([
            'imported_rows' => $this->parent->getImportedCount(),
            'failed_rows' => $this->parent->getFailedCount(),
        ]);
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
