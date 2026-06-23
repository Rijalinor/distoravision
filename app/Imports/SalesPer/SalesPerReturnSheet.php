<?php

namespace App\Imports\SalesPer;

use App\Imports\SalesPerDataImport;
use App\Models\SalesPerTransaction;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/**
 * Sheet "return" — all rows are Returns (type=R)
 * Key columns: Sales Id(D/3), Sales Name(E/4), Outlet Id(S/18), Outlet Name(T/19),
 *              Principle Id(AJ/35), Principle Name(AK/36), Item No(AM/38), Item Name(AN/39),
 *              Qty Base(BB/53), Taxed Amt(BS/70), VAT(BT/71), SO/SN Date(G/6), SO/SN No(H/7), Branch(A/0)
 */
class SalesPerReturnSheet implements ToCollection, WithChunkReading
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
            if ($isFirst) {
                $isFirst = false;

                continue;
            }

            try {
                $branch = trim((string) ($row[0] ?? ''));
                $salesCode = trim((string) ($row[3] ?? ''));

                if (empty($branch) && empty($salesCode)) {
                    continue;
                }

                $soDate = $this->parseExcelDate($row[6] ?? null);

                SalesPerTransaction::create([
                    'sales_per_import_log_id' => $log->id,
                    'type' => 'R',
                    'branch_code' => $branch,
                    'sales_code' => $salesCode,
                    'sales_name' => trim((string) ($row[4] ?? '')),
                    'outlet_code' => trim((string) ($row[18] ?? '')),
                    'outlet_name' => trim((string) ($row[19] ?? '')),
                    'principal_code' => trim((string) ($row[35] ?? '')),
                    'principal_name' => trim((string) ($row[36] ?? '')),
                    'item_no' => trim((string) ($row[38] ?? '')),
                    'item_name' => trim((string) ($row[39] ?? '')),
                    'so_no' => trim((string) ($row[7] ?? '')),
                    'pfi_no' => '',
                    'so_date' => $soDate,
                    'qty' => abs((int) ($row[53] ?? 0)),
                    'subtotal' => abs((float) ($row[70] ?? 0)),
                    'vat' => abs((float) ($row[71] ?? 0)),
                    'period' => $log->period,
                ]);

                $this->parent->incrementImported();
            } catch (\Exception $e) {
                $this->parent->incrementFailed();
                $this->parent->addError('Return Row '.($index + 1).': '.$e->getMessage());
            }
        }

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
