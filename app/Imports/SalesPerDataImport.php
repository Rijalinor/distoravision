<?php

namespace App\Imports;

use App\Models\SalesPerImportLog;
use App\Models\SalesPerStock;
use App\Models\SalesPerTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesPerDataImport implements WithMultipleSheets
{
    protected SalesPerImportLog $importLog;

    protected int $importedCount = 0;

    protected int $failedCount = 0;

    protected array $errors = [];

    public function __construct(SalesPerImportLog $importLog)
    {
        $this->importLog = $importLog;
    }

    public function sheets(): array
    {
        return [
            'penjualan' => new SalesPerPenjualanSheet($this),
            'return' => new SalesPerReturnSheet($this),
            'stock bjm' => new SalesPerStockBjmSheet($this),
            'stock brb' => new SalesPerStockBjmSheet($this),
            'stock btl' => new SalesPerStockBjmSheet($this),
        ];
    }

    public function getImportLog(): SalesPerImportLog
    {
        return $this->importLog;
    }

    public function incrementImported(): void
    {
        $this->importedCount++;
    }

    public function incrementFailed(): void
    {
        $this->failedCount++;
    }

    public function addError(string $msg): void
    {
        if (count($this->errors) < 50) {
            $this->errors[] = $msg;
        }
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

/**
 * Sheet "penjualan" — all rows are Invoices (type=I)
 * Headers: DIST_ID(A), SALES_ID(N), SALES_NAME(O), OUTLET_ID(R), OUTLET_NAME(S),
 *          PRINCIPLE_ID(W), PRINCIPLE_NAME(X), ITEM_NO(AB), ITEM_NAME(AC),
 *          QTY(AF), SUBTOTAL(AM), VAT(AN), SO_DATE(J), SO_NO(F), PFI_NO(D)
 */
class SalesPerPenjualanSheet implements ToCollection, WithChunkReading
{
    protected SalesPerDataImport $parent;

    public function __construct(SalesPerDataImport $parent)
    {
        $this->parent = $parent;
    }

    public function collection(Collection $rows)
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

    protected function parseExcelDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            if (is_numeric($value)) {
                // Excel serial date number
                return Carbon::createFromFormat('Y-m-d', gmdate('Y-m-d', ($value - 25569) * 86400))->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }
}

/**
 * Sheet "return" — all rows are Returns (type=R)
 * This sheet has named headers (WithHeadingRow style), but we read by index for consistency.
 * Key columns: Sales Id(D/3), Sales Name(E/4), Outlet Id(S/18), Outlet Name(T/19),
 *              Principle Id(AJ/35), Principle Name(AK/36), Item No(AM/38), Item Name(AN/39),
 *              Qty Base(BB/53), Taxed Amt(BS/70), VAT(BT/71), SO/SN Date(G/6), SO/SN No(H/7), Branch(A/0)
 */
class SalesPerReturnSheet implements ToCollection, WithChunkReading
{
    protected SalesPerDataImport $parent;

    public function __construct(SalesPerDataImport $parent)
    {
        $this->parent = $parent;
    }

    public function collection(Collection $rows)
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

    protected function parseExcelDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::createFromFormat('Y-m-d', gmdate('Y-m-d', ($value - 25569) * 86400))->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }
}

/**
 * Sheet "stock bjm" — Stock data for Banjarmasin warehouse
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

    public function collection(Collection $rows)
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
