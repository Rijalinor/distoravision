<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\ImportLog;
use App\Models\Outlet;
use App\Models\Principal;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SecondaryDataImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected ImportLog $importLog;
    protected int $importedCount = 0;
    protected int $failedCount = 0;
    protected int $duplicateCount = 0;
    protected array $errors = [];
    protected bool $hasImportLogIdColumn = false;
    protected bool $hasDedupeKeyColumn = false;

    // Caches to avoid repeated DB queries
    protected array $branchCache = [];
    protected array $salesmanCache = [];
    protected array $principalCache = [];
    protected array $outletCache = [];
    protected array $productCache = [];

    public function __construct(ImportLog $importLog)
    {
        $this->importLog = $importLog;
        $this->hasImportLogIdColumn = Schema::hasColumn('transactions', 'import_log_id');
        $this->hasDedupeKeyColumn = Schema::hasColumn('transactions', 'dedupe_key');
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            try {
                $result = $this->processRow($row);
                if ($result === 'imported') {
                    $this->importedCount++;
                } elseif ($result === 'duplicate') {
                    $this->duplicateCount++;
                }
            } catch (\Exception $e) {
                $this->failedCount++;
                if (count($this->errors) < 50) {
                    $this->errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }
        }

        // Update import log periodically
        $errorText = !empty($this->errors) ? implode("\n", $this->errors) : null;
        if ($this->duplicateCount > 0) {
            $dupLine = "Duplicate rows skipped: {$this->duplicateCount}";
            $errorText = $errorText ? ($errorText . "\n" . $dupLine) : $dupLine;
        }

        $this->importLog->update([
            'imported_rows' => $this->importedCount,
            'skipped_rows' => $this->duplicateCount,
            'failed_rows' => $this->failedCount,
            'errors' => $errorText,
        ]);
    }

    protected function processRow(Collection $row): string
    {
        // Skip empty rows
        if (empty($row['branch']) && empty($row['sales_id'])) {
            return 'skipped';
        }

        $this->validateRow($row);

        // 1. Get or create Branch
        $branchId = $this->getOrCreateBranch($row['branch'] ?? '', $row['branch_name'] ?? '');

        // 2. Get or create Salesman
        $salesmanId = $this->getOrCreateSalesman($row['sales_id'] ?? '', $row['sales_name'] ?? '', $branchId);

        // 3. Get or create Principal
        $principalId = $this->getOrCreatePrincipal($row['principle_id'] ?? '', $row['principle_name'] ?? '');

        // 4. Get or create Outlet
        $outletId = $this->getOrCreateOutlet(
            $row['outlet_id'] ?? '',
            $row['outlet_name'] ?? '',
            $row['outlet_address'] ?? '',
            $row['outlet_city'] ?? '',
            $row['route'] ?? '',
            $row['outlet_phone'] ?? ''
        );

        // 5. Get or create Product
        $productId = $this->getOrCreateProduct(
            $principalId,
            $row['item_no'] ?? '',
            $row['item_name'] ?? '',
            $row['uom_sku'] ?? ''
        );

        // 6. Parse dates
        $soDate = $this->parseDate($row['sosn_date'] ?? $row['so_sn_date'] ?? null);
        $pfiDate = $this->parseDate($row['pficn_date'] ?? $row['pfi_cn_date'] ?? null);
        $giDate = $this->parseDate($row['gigr_date'] ?? $row['gi_gr_date'] ?? null);

        // 7. Determine period from SO date or month field
        $period = $this->importLog->period;

        // 8. Parse numeric values (handle comma-formatted numbers like "4,691")
        $qtyBase = $this->parseNumber($row['qty_base'] ?? 0);
        $priceBase = $this->parseNumber($row['price_base'] ?? 0);
        $gross = $this->parseNumber($row['gross'] ?? 0);
        $discTotal = $this->parseNumber($row['disc_total'] ?? 0);
        $taxedAmt = $this->parseNumber($row['taxed_amt'] ?? 0);
        $vat = $this->parseNumber($row['vat'] ?? 0);
        $arAmt = $this->parseNumber($row['ar_amt'] ?? 0);
        $cogs = $this->parseNumber($row['cogs'] ?? 0);

        $type = strtoupper(trim((string) ($row['type'] ?? 'I')));
        $soNoRaw = trim((string) ($row['sosn_no'] ?? $row['so_sn_no'] ?? ''));
        $soNo = $soNoRaw !== '' ? strtoupper($soNoRaw) : '';
        $dedupeKey = $this->buildDedupeKey($period, $type, $soNo, $outletId, $productId, $salesmanId, $qtyBase, $arAmt);
        $payload = [
            'branch_id' => $branchId,
            'salesman_id' => $salesmanId,
            'outlet_id' => $outletId,
            'product_id' => $productId,
            'type' => $type,
            'so_no' => $soNo !== '' ? $soNo : null,
            'so_date' => $soDate,
            'ref_no' => $row['ref_no'] ?? null,
            'pfi_cn_no' => $row['pficn_no'] ?? $row['pfi_cn_no_2'] ?? null,
            'pfi_cn_date' => $pfiDate,
            'gi_gr_no' => $row['gigr_no'] ?? $row['gi_gr_no'] ?? null,
            'gi_gr_date' => $giDate,
            'si_cn_no' => $row['sicn_no'] ?? $row['si_cn_no'] ?? null,
            'month' => $row['month'] ?? null,
            'week' => !empty($row['week']) ? (int) $row['week'] : null,
            'warehouse' => $row['warehouse'] ?? null,
            'tax_invoice' => $row['tax_invoice'] ?? null,
            'qty_base' => $qtyBase,
            'price_base' => $priceBase,
            'gross' => $gross,
            'disc_total' => $discTotal,
            'taxed_amt' => $taxedAmt,
            'vat' => $vat,
            'ar_amt' => $arAmt,
            'cogs' => $cogs,
            'period' => $period,
        ];

        if ($this->hasImportLogIdColumn) {
            $payload['import_log_id'] = $this->importLog->id;
        }

        // 9. Create Transaction (idempotent with dedupe key)
        if ($this->hasDedupeKeyColumn) {
            $existingDuplicate = $this->findExistingDuplicate(
                $dedupeKey,
                $period,
                $type,
                $soNo,
                $outletId,
                $productId,
                $salesmanId,
                $qtyBase,
                $arAmt
            );

            if ($existingDuplicate !== null) {
                // Backfill dedupe_key on legacy rows so next imports are faster.
                if ($existingDuplicate->dedupe_key === null) {
                    Transaction::withoutGlobalScopes()
                        ->whereKey($existingDuplicate->id)
                        ->update(['dedupe_key' => $dedupeKey]);
                }

                return 'duplicate';
            }

            $transaction = Transaction::withoutGlobalScopes()->firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                array_merge($payload, ['dedupe_key' => $dedupeKey])
            );

            return $transaction->wasRecentlyCreated ? 'imported' : 'duplicate';
        }

        // Backward compatibility when dedupe column does not exist yet.
        Transaction::withoutGlobalScopes()->create($payload);
        return 'imported';
    }

    protected function findExistingDuplicate(
        string $dedupeKey,
        string $period,
        string $type,
        string $soNo,
        int $outletId,
        int $productId,
        int $salesmanId,
        float $qtyBase,
        float $arAmt
    ): ?Transaction {
        $normalizedSoNo = $soNo !== '' ? $soNo : '';
        $formattedQty = number_format($qtyBase, 4, '.', '');
        $formattedArAmt = number_format($arAmt, 4, '.', '');

        return Transaction::withoutGlobalScopes()
            ->where(function ($q) use (
                $dedupeKey,
                $period,
                $type,
                $normalizedSoNo,
                $outletId,
                $productId,
                $salesmanId,
                $formattedQty,
                $formattedArAmt
            ) {
                $q->where('dedupe_key', $dedupeKey)
                    ->orWhere(function ($legacy) use (
                        $period,
                        $type,
                        $normalizedSoNo,
                        $outletId,
                        $productId,
                        $salesmanId,
                        $formattedQty,
                        $formattedArAmt
                    ) {
                        $legacy->whereNull('dedupe_key')
                            ->where('period', $period)
                            ->where('type', $type)
                            ->where('outlet_id', $outletId)
                            ->where('product_id', $productId)
                            ->where('salesman_id', $salesmanId)
                            ->where('qty_base', $formattedQty)
                            ->where('ar_amt', $formattedArAmt)
                            ->whereRaw('UPPER(COALESCE(so_no, "")) = ?', [$normalizedSoNo]);
                    });
            })
            ->select('id', 'dedupe_key')
            ->first();
    }

    protected function validateRow(Collection $row): void
    {
        $required = [
            'branch' => trim((string) ($row['branch'] ?? '')),
            'sales_id' => trim((string) ($row['sales_id'] ?? '')),
            'outlet_id' => trim((string) ($row['outlet_id'] ?? '')),
            'item_no' => trim((string) ($row['item_no'] ?? '')),
        ];

        foreach ($required as $field => $value) {
            if ($value === '') {
                throw new \InvalidArgumentException("Field '{$field}' wajib diisi.");
            }
        }

        $type = strtoupper(trim((string) ($row['type'] ?? 'I')));
        if (!in_array($type, ['I', 'R'], true)) {
            throw new \InvalidArgumentException("Field 'type' harus I atau R.");
        }
    }

    protected function buildDedupeKey(
        string $period,
        string $type,
        string $soNo,
        int $outletId,
        int $productId,
        int $salesmanId,
        float $qtyBase,
        float $arAmt
    ): string {
        $normalizedSoNo = $soNo !== '' ? $soNo : 'NO_SO';
        $parts = [
            $period,
            $type,
            $normalizedSoNo,
            (string) $outletId,
            (string) $productId,
            (string) $salesmanId,
            number_format($qtyBase, 4, '.', ''),
            number_format($arAmt, 4, '.', ''),
        ];

        return sha1(implode('|', $parts));
    }

    protected function getOrCreateBranch(string $code, string $name): int
    {
        $code = trim($code);
        if (isset($this->branchCache[$code])) {
            return $this->branchCache[$code];
        }

        $branch = Branch::firstOrCreate(
            ['code' => $code],
            ['name' => trim($name)]
        );

        $this->branchCache[$code] = $branch->id;
        return $branch->id;
    }

    protected function getOrCreateSalesman(string $salesCode, string $name, int $branchId): int
    {
        $salesCode = trim($salesCode);
        if (isset($this->salesmanCache[$salesCode])) {
            return $this->salesmanCache[$salesCode];
        }

        $salesman = Salesman::firstOrCreate(
            ['sales_code' => $salesCode],
            ['name' => trim($name), 'branch_id' => $branchId]
        );

        $this->salesmanCache[$salesCode] = $salesman->id;
        return $salesman->id;
    }

    protected function getOrCreatePrincipal(string $code, string $name): int
    {
        $name = trim($name);
        $cacheKey = $code ?: $name;
        if (isset($this->principalCache[$cacheKey])) {
            return $this->principalCache[$cacheKey];
        }

        $principal = Principal::firstOrCreate(
            ['name' => $name],
            ['code' => trim($code) ?: null]
        );

        $this->principalCache[$cacheKey] = $principal->id;
        return $principal->id;
    }

    protected function getOrCreateOutlet(string $code, string $name, ?string $address, ?string $city, ?string $route, ?string $phone): int
    {
        $code = trim($code);
        if (isset($this->outletCache[$code])) {
            return $this->outletCache[$code];
        }

        $outlet = Outlet::firstOrCreate(
            ['code' => $code],
            [
                'name' => trim($name),
                'address' => trim($address ?? ''),
                'city' => trim($city ?? ''),
                'route' => trim($route ?? ''),
                'phone' => trim($phone ?? ''),
            ]
        );

        $this->outletCache[$code] = $outlet->id;
        return $outlet->id;
    }

    protected function getOrCreateProduct(int $principalId, string $itemNo, string $name, ?string $uomSku): int
    {
        $itemNo = trim($itemNo);
        $cacheKey = $principalId . ':' . $itemNo;
        if (isset($this->productCache[$cacheKey])) {
            return $this->productCache[$cacheKey];
        }

        $product = Product::firstOrCreate(
            ['principal_id' => $principalId, 'item_no' => $itemNo],
            ['name' => trim($name), 'uom_sku' => trim($uomSku ?? '')]
        );

        $this->productCache[$cacheKey] = $product->id;
        return $product->id;
    }

    protected function parseDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        try {
            // Try dd-mm-yyyy format first (as seen in the data)
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dateStr)) {
                return Carbon::createFromFormat('d-m-Y', $dateStr)->format('Y-m-d');
            }
            // Try other common formats
            return Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function parseNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            // Remove quotes and spaces
            $value = trim($value, "\" '");
            // Remove thousand separators (commas in Indonesian format)
            $value = str_replace(',', '', $value);
            // Handle empty
            if (empty($value) || $value === '-') {
                return 0;
            }
            return (float) $value;
        }

        return 0;
    }

    public function chunkSize(): int
    {
        return 500;
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

    public function getDuplicateCount(): int
    {
        return $this->duplicateCount;
    }
}
