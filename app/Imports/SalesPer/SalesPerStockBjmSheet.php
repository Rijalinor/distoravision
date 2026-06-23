<?php

namespace App\Imports\SalesPer;

use App\Imports\SalesPerDataImport;
use App\Models\SalesPerStock;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/**
 * Sheet "stock bjm" / "stock brb" / "stock btl" — Stock data for warehouse branches
 * Columns: Principle#(A/0), Principle Description(B/1), Warehouse#(C/2), Warehouse Description(D/3),
 *          Location#(E/4), Location Description(F/5), Item#(G/6), Item Description(H/7), Size(I/8),
 *          OnHand(J/9), OnSales(K/10), OnHand Base(L/11), OnSales Base(M/12),
 *          Stock Value OnHand(N/13), Stock Value OnSales(O/14), Tonnage(P/15),
 *          WAS(Q/16), SWC(R/17), Age of Goods(S/18)
 */
class SalesPerStockBjmSheet implements ToCollection, WithChunkReading
{
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
                $principalCode = trim((string) ($row[0] ?? ''));
                $itemNo = trim((string) ($row[6] ?? ''));

                if (empty($principalCode) && empty($itemNo)) {
                    continue;
                }

                SalesPerStock::create([
                    'sales_per_import_log_id' => $log->id,
                    'principal_code' => $principalCode,
                    'principal_name' => trim((string) ($row[1] ?? '')),
                    'warehouse_code' => trim((string) ($row[2] ?? '')),
                    'warehouse_name' => trim((string) ($row[3] ?? '')),
                    'item_no' => $itemNo,
                    'item_name' => trim((string) ($row[7] ?? '')),
                    'size' => trim((string) ($row[8] ?? '')),
                    'on_hand_base' => (int) ($row[11] ?? 0),
                    'on_sales_base' => (int) ($row[12] ?? 0),
                    'stock_value_on_hand' => (float) ($row[13] ?? 0),
                    'stock_value_on_sales' => (float) ($row[14] ?? 0),
                    'was' => (float) ($row[16] ?? 0),
                    'swc' => (float) ($row[17] ?? 0),
                    'age_of_goods' => (int) ($row[18] ?? 0),
                    'period' => $log->period,
                ]);

                $this->parent->incrementImported();
            } catch (\Exception $e) {
                $this->parent->incrementFailed();
                $this->parent->addError('Stock BJM Row '.($index + 1).': '.$e->getMessage());
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
