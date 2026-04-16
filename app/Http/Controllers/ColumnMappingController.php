<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ColumnMappingController extends Controller
{
    /**
     * Human-readable labels for each mapping key, grouped by category.
     */
    protected function getFieldGroups(): array
    {
        return [
            'Branch' => [
                'branch'      => 'Kode Branch',
                'branch_name' => 'Nama Branch',
            ],
            'Sales' => [
                'sales_id'   => 'Sales ID',
                'sales_name' => 'Nama Sales',
            ],
            'Principal' => [
                'principle_id'   => 'Kode Principal',
                'principle_name' => 'Nama Principal',
            ],
            'Outlet' => [
                'outlet_id'      => 'Kode Outlet',
                'outlet_name'    => 'Nama Outlet',
                'outlet_address' => 'Alamat Outlet',
                'outlet_city'    => 'Kota Outlet',
                'outlet_phone'   => 'Telepon Outlet',
                'route'          => 'Route',
            ],
            'Product' => [
                'item_no'   => 'Item No',
                'item_name' => 'Nama Item',
                'uom_sku'   => 'UOM / SKU',
            ],
            'Detail Transaksi' => [
                'type'        => 'Type (I/R)',
                'sosn_no'     => 'SO/SN No',
                'so_sn_no'    => 'SO/SN No (Alt)',
                'sosn_date'   => 'SO/SN Date',
                'so_sn_date'  => 'SO/SN Date (Alt)',
                'ref_no'      => 'Ref No',
                'pficn_no'    => 'PFI/CN No',
                'pfi_cn_no_2' => 'PFI/CN No (Alt)',
                'pficn_date'  => 'PFI/CN Date',
                'pfi_cn_date' => 'PFI/CN Date (Alt)',
                'gigr_no'     => 'GI/GR No',
                'gi_gr_no'    => 'GI/GR No (Alt)',
                'gigr_date'   => 'GI/GR Date',
                'gi_gr_date'  => 'GI/GR Date (Alt)',
                'sicn_no'     => 'SI/CN No',
                'si_cn_no'    => 'SI/CN No (Alt)',
                'month'       => 'Month',
                'week'        => 'Week',
                'warehouse'   => 'Warehouse',
                'tax_invoice' => 'Tax Invoice',
            ],
            'Nominal' => [
                'qty_base'   => 'Qty Base',
                'price_base' => 'Price Base',
                'gross'      => 'Gross',
                'disc_total' => 'Disc Total',
                'taxed_amt'  => 'Taxed Amount',
                'vat'        => 'VAT',
                'ar_amt'     => 'AR Amount',
                'cogs'       => 'COGS',
            ],
        ];
    }

    public function edit()
    {
        $fieldGroups = $this->getFieldGroups();
        $currentMapping = config('import_columns', []);

        return view('settings.column-mapping', compact('fieldGroups', 'currentMapping'));
    }

    public function update(Request $request)
    {
        $fieldGroups = $this->getFieldGroups();
        $allKeys = collect($fieldGroups)->flatMap(fn($fields) => array_keys($fields))->all();

        // Validate & collect
        $mapping = [];
        foreach ($allKeys as $key) {
            $value = trim($request->input("columns.{$key}", $key));
            // Normalize to snake_case-like format matching Maatwebsite heading row behavior
            $mapping[$key] = $value !== '' ? $value : $key;
        }

        // Build the config file content
        $lines = ["<?php\n"];
        $lines[] = "/**";
        $lines[] = " * ========================================================================";
        $lines[] = " * IMPORT COLUMN MAPPING";
        $lines[] = " * ========================================================================";
        $lines[] = " *";
        $lines[] = " * File ini di-generate otomatis dari halaman Settings > Column Mapping.";
        $lines[] = " * Anda juga bisa mengedit file ini langsung jika diperlukan.";
        $lines[] = " *";
        $lines[] = " * KEY SISTEM (kiri) JANGAN DIUBAH.";
        $lines[] = " * ========================================================================";
        $lines[] = " */\n";
        $lines[] = "return [\n";

        foreach ($fieldGroups as $groupName => $fields) {
            $lines[] = "    // ── {$groupName} " . str_repeat('─', max(1, 60 - strlen($groupName)));
            foreach ($fields as $key => $label) {
                $val = $mapping[$key];
                $lines[] = "    '{$key}' => '{$val}',";
            }
            $lines[] = "";
        }

        $lines[] = "];\n";

        $configPath = config_path('import_columns.php');
        file_put_contents($configPath, implode("\n", $lines));

        // Clear cached config so changes take effect immediately
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($configPath, true);
        }

        try {
            \Artisan::call('config:clear');
        } catch (\Exception $e) {
            // Not critical if it fails
        }

        return redirect()
            ->route('settings.column-mapping')
            ->with('success', 'Column mapping berhasil disimpan! Perubahan akan berlaku pada import berikutnya.');
    }
}
