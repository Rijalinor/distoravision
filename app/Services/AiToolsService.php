<?php

namespace App\Services;

use App\Models\ArImportLog;
use App\Models\ArReceivable;
use App\Models\SalesPerStock;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiToolsService
{
    /**
     * Get the tool definitions in OpenAI-compatible JSON Schema format.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_entities',
                    'description' => 'Cari kandidat produk, outlet/toko, salesman, atau principal berdasarkan keyword. Gunakan saat nama user tidak persis, ambigu, atau sebelum query detail yang membutuhkan entitas spesifik.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'entity_type' => [
                                'type' => 'string',
                                'enum' => ['product', 'outlet', 'salesman', 'principal', 'all'],
                                'description' => 'Jenis entitas yang dicari. Gunakan all jika user menyebut nama tapi jenisnya belum jelas.',
                            ],
                            'query' => [
                                'type' => 'string',
                                'description' => 'Keyword nama/kode yang dicari.',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Jumlah kandidat per jenis entitas (default 5, max 10).',
                            ],
                        ],
                        'required' => ['entity_type', 'query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_filtered_sales',
                    'description' => 'Ambil penjualan dengan kombinasi filter bebas: produk, outlet/toko, salesman, principal, dan periode. Gunakan untuk pertanyaan seperti "penjualan Indomie di toko Sumber Jaya bulan Maret", "omset salesman Budi untuk principal Wings", atau "produk apa saja yang dibeli toko X".',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'period' => [
                                'type' => 'string',
                                'description' => 'Periode YYYY-MM. Jika kosong gunakan periode terbaru.',
                            ],
                            'product' => ['type' => 'string', 'description' => 'Nama/kode produk opsional.'],
                            'outlet' => ['type' => 'string', 'description' => 'Nama/kode outlet atau toko opsional.'],
                            'salesman' => ['type' => 'string', 'description' => 'Nama/kode salesman opsional.'],
                            'principal' => ['type' => 'string', 'description' => 'Nama/kode principal opsional.'],
                            'type' => [
                                'type' => 'string',
                                'enum' => ['invoice', 'return', 'net', 'all'],
                                'description' => 'Jenis transaksi. invoice hanya penjualan, return hanya retur, net/all menghitung invoice dikurangi retur. Default net.',
                            ],
                            'group_by' => [
                                'type' => 'string',
                                'enum' => ['none', 'product', 'outlet', 'salesman', 'principal'],
                                'description' => 'Kelompokkan hasil jika user meminta daftar/breakdown. Default none.',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Jumlah baris breakdown (default 10, max 30).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_sales_trend',
                    'description' => 'Ambil tren penjualan per bulan untuk filter produk/outlet/salesman/principal. Gunakan untuk pertanyaan "trend 6 bulan", "naik atau turun", atau performa historis.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product' => ['type' => 'string', 'description' => 'Nama/kode produk opsional.'],
                            'outlet' => ['type' => 'string', 'description' => 'Nama/kode outlet opsional.'],
                            'salesman' => ['type' => 'string', 'description' => 'Nama/kode salesman opsional.'],
                            'principal' => ['type' => 'string', 'description' => 'Nama/kode principal opsional.'],
                            'months' => [
                                'type' => 'integer',
                                'description' => 'Jumlah bulan terakhir yang diambil (default 6, max 24).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'explain_sales_change',
                    'description' => 'Jelaskan penyebab perubahan sales antara dua periode dengan breakdown kontributor naik/turun berdasarkan produk, outlet, salesman, atau principal.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'period_a' => [
                                'type' => 'string',
                                'description' => 'Periode terbaru/yang dibandingkan, format YYYY-MM.',
                            ],
                            'period_b' => [
                                'type' => 'string',
                                'description' => 'Periode pembanding/sebelumnya, format YYYY-MM.',
                            ],
                            'dimension' => [
                                'type' => 'string',
                                'enum' => ['product', 'outlet', 'salesman', 'principal'],
                                'description' => 'Dimensi breakdown penyebab perubahan. Default product.',
                            ],
                            'product' => ['type' => 'string', 'description' => 'Filter produk opsional.'],
                            'outlet' => ['type' => 'string', 'description' => 'Filter outlet opsional.'],
                            'salesman' => ['type' => 'string', 'description' => 'Filter salesman opsional.'],
                            'principal' => ['type' => 'string', 'description' => 'Filter principal opsional.'],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Jumlah kontributor naik dan turun (default 5, max 15).',
                            ],
                        ],
                        'required' => ['period_a', 'period_b'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_sales_summary',
                    'description' => 'Ambil ringkasan KPI finansial perusahaan: omset kotor, retur, penjualan bersih (net sales), margin kotor, total COGS, dan jumlah invoice/return. Gunakan untuk pertanyaan umum tentang performa bisnis, omset, atau margin.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'period' => [
                                'type' => 'string',
                                'description' => 'Periode dalam format YYYY-MM, contoh: 2026-05. Jika tidak disebutkan, gunakan periode terbaru.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_top_entities',
                    'description' => 'Ambil peringkat entitas terbaik atau terburuk berdasarkan penjualan. Bisa untuk produk, outlet/toko, atau salesman. Gunakan untuk pertanyaan seperti "produk terlaris", "toko terbesar", "salesman terbaik", atau sebaliknya "produk paling tidak laku".',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'entity_type' => [
                                'type' => 'string',
                                'enum' => ['product', 'outlet', 'salesman'],
                                'description' => 'Jenis entitas: product (produk/barang/SKU), outlet (toko/pelanggan), atau salesman.',
                            ],
                            'period' => [
                                'type' => 'string',
                                'description' => 'Periode YYYY-MM. Jika tidak disebutkan, gunakan periode terbaru.',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Jumlah data yang diambil (default 5, max 20). WAJIB berupa angka/integer (contoh: 5), BUKAN string dengan tanda kutip (contoh: "5").',
                            ],
                            'order' => [
                                'type' => 'string',
                                'enum' => ['top', 'bottom'],
                                'description' => 'Urutan: top (terbaik/terlaris) atau bottom (terburuk/paling sedikit). Default: top.',
                            ],
                        ],
                        'required' => ['entity_type'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_entity_detail',
                    'description' => 'Ambil detail penjualan untuk SATU entitas spesifik berdasarkan pencarian nama. Gunakan saat user menanyakan produk, toko, atau salesman tertentu, contoh: "berapa penjualan Indomie?", "performa salesman Ahmad?".',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'entity_type' => [
                                'type' => 'string',
                                'enum' => ['product', 'outlet', 'salesman'],
                                'description' => 'Jenis entitas.',
                            ],
                            'name' => [
                                'type' => 'string',
                                'description' => 'Nama atau sebagian nama entitas untuk dicari (case-insensitive LIKE search).',
                            ],
                            'period' => [
                                'type' => 'string',
                                'description' => 'Periode YYYY-MM. Jika tidak disebutkan, gunakan periode terbaru.',
                            ],
                        ],
                        'required' => ['entity_type', 'name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_stock_alerts',
                    'description' => 'Ambil data stok barang: stok kritis (hampir habis, SWC < 2), overstock (kelebihan, SWC > 12), atau semua stok. SWC = Stock Weeks Cover (perkiraan berapa minggu stok bertahan).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'alert_type' => [
                                'type' => 'string',
                                'enum' => ['critical', 'overstock', 'all'],
                                'description' => 'Jenis alert: critical (SWC<2), overstock (SWC>12), all (semua).',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Jumlah data (default 10, max 30). WAJIB berupa angka/integer (contoh: 10), BUKAN string dengan tanda kutip (contoh: "10").',
                            ],
                        ],
                        'required' => ['alert_type'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_ar_receivables',
                    'description' => 'Ambil data piutang (Account Receivable). Bisa filter berdasarkan status jatuh tempo. Gunakan untuk pertanyaan tentang piutang, tunggakan, atau utang toko.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'filter' => [
                                'type' => 'string',
                                'enum' => ['overdue', 'all', 'critical'],
                                'description' => 'Filter: overdue (semua jatuh tempo), critical (jatuh tempo > 60 hari), all (semua piutang aktif).',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Jumlah data (default 10, max 30). WAJIB berupa angka/integer (contoh: 10), BUKAN string dengan tanda kutip (contoh: "10").',
                            ],
                            'search' => [
                                'type' => 'string',
                                'description' => 'Cari berdasarkan nama toko (opsional).',
                            ],
                        ],
                        'required' => ['filter'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'compare_periods',
                    'description' => 'Bandingkan KPI antara dua periode. Menampilkan omset, retur, net sales, margin, dan pertumbuhan (MoM %). Gunakan saat user bertanya perbandingan "bulan ini vs bulan lalu" atau antara 2 bulan tertentu.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'period_a' => [
                                'type' => 'string',
                                'description' => 'Periode pertama (YYYY-MM), biasanya bulan sekarang/terbaru.',
                            ],
                            'period_b' => [
                                'type' => 'string',
                                'description' => 'Periode kedua (YYYY-MM), biasanya bulan sebelumnya.',
                            ],
                        ],
                        'required' => ['period_a', 'period_b'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_available_periods',
                    'description' => 'Ambil daftar periode (bulan) yang tersedia di database. Gunakan saat perlu tahu periode apa saja yang ada, atau saat user tidak menyebutkan periode spesifik.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a tool call and return the result.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function executeTool(string $toolName, array $arguments): string
    {
        try {
            $result = match ($toolName) {
                'search_entities' => $this->searchEntities($arguments),
                'get_filtered_sales' => $this->getFilteredSales($arguments),
                'get_sales_trend' => $this->getSalesTrend($arguments),
                'explain_sales_change' => $this->explainSalesChange($arguments),
                'get_sales_summary' => $this->getSalesSummary($arguments),
                'get_top_entities' => $this->getTopEntities($arguments),
                'get_entity_detail' => $this->getEntityDetail($arguments),
                'get_stock_alerts' => $this->getStockAlerts($arguments),
                'get_ar_receivables' => $this->getArReceivables($arguments),
                'compare_periods' => $this->comparePeriods($arguments),
                'get_available_periods' => $this->getAvailablePeriods(),
                default => json_encode(['error' => "Tool '{$toolName}' tidak dikenal."]),
            };
        } catch (\Throwable $e) {
            Log::warning('AI tool execution failed', [
                'tool' => $toolName,
                'arguments' => $arguments,
                'message' => $e->getMessage(),
            ]);

            return json_encode([
                'error' => 'Data tidak dapat diambil untuk pertanyaan ini. Silakan coba dengan parameter yang lebih spesifik.',
            ], JSON_UNESCAPED_UNICODE);
        }

        return is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    private function getLatestPeriod(): string
    {
        return Transaction::max('period') ?? date('Y-m');
    }

    private function clampLimit(mixed $limit, int $default, int $max): int
    {
        return min(max((int) ($limit ?? $default), 1), $max);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function baseSalesQuery(array $args)
    {
        $query = Transaction::query()
            ->leftJoin('products', 'transactions.product_id', '=', 'products.id')
            ->leftJoin('principals', 'products.principal_id', '=', 'principals.id')
            ->leftJoin('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->leftJoin('salesmen', 'transactions.salesman_id', '=', 'salesmen.id');

        if (! empty($args['period'])) {
            $query->where('transactions.period', $args['period']);
        }

        foreach ([
            'product' => ['products.name', 'products.item_no'],
            'outlet' => ['outlets.name', 'outlets.code'],
            'salesman' => ['salesmen.name', 'salesmen.sales_code'],
            'principal' => ['principals.name', 'principals.code'],
        ] as $argument => $columns) {
            if (empty($args[$argument])) {
                continue;
            }

            $keyword = trim((string) $args[$argument]);
            $query->where(function ($q) use ($columns, $keyword) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$keyword}%");
                }
            });
        }

        $type = $args['type'] ?? 'net';
        if ($type === 'invoice') {
            $query->where('transactions.type', 'I');
        } elseif ($type === 'return') {
            $query->where('transactions.type', 'R');
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    private function groupConfig(string $groupBy): array
    {
        return match ($groupBy) {
            'product' => ['id' => 'products.id', 'name' => 'products.name'],
            'outlet' => ['id' => 'outlets.id', 'name' => 'outlets.name'],
            'salesman' => ['id' => 'salesmen.id', 'name' => 'salesmen.name'],
            'principal' => ['id' => 'principals.id', 'name' => 'principals.name'],
            default => ['id' => '', 'name' => ''],
        };
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function searchEntities(array $args): string
    {
        $entityType = $args['entity_type'];
        $keyword = trim((string) $args['query']);
        $limit = $this->clampLimit($args['limit'] ?? null, 5, 10);

        $search = function (string $table, string $nameColumn, array $extraColumns = []) use ($keyword, $limit) {
            $query = DB::table($table)
                ->select(array_merge(['id', "{$nameColumn} as name"], $extraColumns))
                ->where($nameColumn, 'LIKE', "%{$keyword}%");

            foreach ($extraColumns as $columnExpression) {
                $column = str_contains($columnExpression, ' as ')
                    ? trim(strtok($columnExpression, ' '))
                    : $columnExpression;
                $query->orWhere($column, 'LIKE', "%{$keyword}%");
            }

            return $query->orderByRaw("CASE WHEN {$nameColumn} LIKE ? THEN 0 ELSE 1 END", [$keyword.'%'])
                ->orderBy($nameColumn)
                ->limit($limit)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();
        };

        $types = $entityType === 'all'
            ? ['product', 'outlet', 'salesman', 'principal']
            : [$entityType];

        $results = [];
        foreach ($types as $type) {
            $results[$type] = match ($type) {
                'product' => $search('products', 'name', ['item_no']),
                'outlet' => $search('outlets', 'name', ['code', 'city']),
                'salesman' => $search('salesmen', 'name', ['sales_code']),
                'principal' => $search('principals', 'name', ['code']),
                default => [],
            };
        }

        return json_encode([
            'query' => $keyword,
            'limit_per_entity' => $limit,
            'results' => $results,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getFilteredSales(array $args): string
    {
        $period = $args['period'] ?? $this->getLatestPeriod();
        $groupBy = $args['group_by'] ?? 'none';
        $limit = $this->clampLimit($args['limit'] ?? null, 10, 30);

        $queryArgs = array_merge($args, ['period' => $period]);
        $query = $this->baseSalesQuery($queryArgs);

        $selects = [
            DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) as omset_kotor'),
            DB::raw('SUM(CASE WHEN transactions.type = "R" THEN ABS(transactions.taxed_amt) ELSE 0 END) as retur'),
            DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs WHEN transactions.type = "R" THEN -ABS(transactions.cogs) ELSE 0 END) as cogs'),
            DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.disc_total ELSE 0 END) as diskon'),
            DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.qty_base ELSE 0 END) as qty_invoice'),
            DB::raw('SUM(CASE WHEN transactions.type = "R" THEN ABS(transactions.qty_base) ELSE 0 END) as qty_return'),
            DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.so_no END) as jumlah_invoice'),
            DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "R" THEN transactions.so_no END) as jumlah_retur'),
        ];

        if ($groupBy !== 'none') {
            $cfg = $this->groupConfig($groupBy);
            $rows = $query
                ->select(array_merge([
                    "{$cfg['id']} as entity_id",
                    "{$cfg['name']} as nama",
                ], $selects))
                ->groupBy($cfg['id'], $cfg['name'])
                ->orderByDesc(DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) - SUM(CASE WHEN transactions.type = "R" THEN ABS(transactions.taxed_amt) ELSE 0 END)'))
                ->limit($limit)
                ->get()
                ->map(fn ($row) => $this->formatSalesRow($row));

            return json_encode([
                'periode' => $period,
                'filters' => $this->visibleFilters($queryArgs),
                'group_by' => $groupBy,
                'count' => $rows->count(),
                'data' => $rows->toArray(),
            ], JSON_UNESCAPED_UNICODE);
        }

        $row = $query->select($selects)->first();

        return json_encode([
            'periode' => $period,
            'filters' => $this->visibleFilters($queryArgs),
            'summary' => $this->formatSalesRow($row),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function formatSalesRow(mixed $row): array
    {
        $omset = (float) ($row->omset_kotor ?? 0);
        $retur = (float) ($row->retur ?? 0);
        $net = $omset - $retur;
        $cogs = (float) ($row->cogs ?? 0);
        $profit = $net - $cogs;

        $data = [
            'omset_kotor' => $omset,
            'retur' => $retur,
            'net_sales' => $net,
            'cogs' => $cogs,
            'laba_kotor' => $profit,
            'margin_persen' => $net > 0 ? round(($profit / $net) * 100, 2) : 0,
            'total_diskon' => (float) ($row->diskon ?? 0),
            'qty_invoice' => (int) ($row->qty_invoice ?? 0),
            'qty_return' => (int) ($row->qty_return ?? 0),
            'jumlah_invoice' => (int) ($row->jumlah_invoice ?? 0),
            'jumlah_retur' => (int) ($row->jumlah_retur ?? 0),
        ];

        if (isset($row->entity_id) || isset($row->nama)) {
            $data = array_merge([
                'entity_id' => $row->entity_id ?? null,
                'nama' => $row->nama ?? 'Unknown',
            ], $data);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, string>
     */
    private function visibleFilters(array $args): array
    {
        return collect($args)
            ->only(['product', 'outlet', 'salesman', 'principal', 'type'])
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (string) $value)
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getSalesTrend(array $args): string
    {
        $months = $this->clampLimit($args['months'] ?? null, 6, 24);
        $periods = Transaction::select('period')
            ->distinct()
            ->orderByDesc('period')
            ->limit($months)
            ->pluck('period')
            ->sort()
            ->values()
            ->toArray();

        if (empty($periods)) {
            return json_encode(['pesan' => 'Belum ada data transaksi untuk membuat tren.'], JSON_UNESCAPED_UNICODE);
        }

        $query = $this->baseSalesQuery($args)
            ->whereIn('transactions.period', $periods)
            ->select(
                'transactions.period',
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) as omset_kotor'),
                DB::raw('SUM(CASE WHEN transactions.type = "R" THEN ABS(transactions.taxed_amt) ELSE 0 END) as retur'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs WHEN transactions.type = "R" THEN -ABS(transactions.cogs) ELSE 0 END) as cogs'),
                DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.so_no END) as jumlah_invoice')
            )
            ->groupBy('transactions.period')
            ->orderBy('transactions.period');

        $rows = $query->get();
        $previousNet = null;
        $trend = $rows->map(function ($row) use (&$previousNet) {
            $sales = $this->formatSalesRow($row);
            $growth = $previousNet && $previousNet > 0
                ? round((($sales['net_sales'] - $previousNet) / $previousNet) * 100, 2)
                : null;
            $previousNet = $sales['net_sales'];

            return array_merge(['periode' => $row->period], $sales, ['growth_vs_prev_persen' => $growth]);
        });

        return json_encode([
            'periode_range' => ['from' => $periods[0], 'to' => end($periods)],
            'filters' => $this->visibleFilters($args),
            'months_requested' => $months,
            'data' => $trend->toArray(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function explainSalesChange(array $args): string
    {
        $periodA = $args['period_a'];
        $periodB = $args['period_b'];
        $dimension = $args['dimension'] ?? 'product';
        $limit = $this->clampLimit($args['limit'] ?? null, 5, 15);
        $cfg = $this->groupConfig($dimension);

        $filters = collect($args)->except(['period_a', 'period_b', 'dimension', 'limit'])->toArray();

        $rowsFor = function (string $period) use ($filters, $cfg) {
            return $this->baseSalesQuery(array_merge($filters, ['period' => $period]))
                ->select(
                    "{$cfg['id']} as entity_id",
                    "{$cfg['name']} as nama",
                    DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) - SUM(CASE WHEN transactions.type = "R" THEN ABS(transactions.taxed_amt) ELSE 0 END) as net_sales')
                )
                ->groupBy($cfg['id'], $cfg['name'])
                ->get()
                ->keyBy('entity_id');
        };

        $aRows = $rowsFor($periodA);
        $bRows = $rowsFor($periodB);
        $allIds = $aRows->keys()->merge($bRows->keys())->unique();

        $changes = $allIds->map(function ($id) use ($aRows, $bRows, $periodA, $periodB) {
            $a = $aRows->get($id);
            $b = $bRows->get($id);
            $now = (float) ($a->net_sales ?? 0);
            $prev = (float) ($b->net_sales ?? 0);

            return [
                'entity_id' => $id,
                'nama' => $a->nama ?? $b->nama ?? 'Unknown',
                "net_sales_{$periodA}" => $now,
                "net_sales_{$periodB}" => $prev,
                'selisih_rp' => $now - $prev,
                'growth_persen' => $prev > 0 ? round((($now - $prev) / $prev) * 100, 2) : null,
            ];
        });

        $topDrivers = $changes->sortByDesc('selisih_rp')->take($limit)->values();
        $bottomDrivers = $changes->sortBy('selisih_rp')->take($limit)->values();

        $totalA = $changes->sum("net_sales_{$periodA}");
        $totalB = $changes->sum("net_sales_{$periodB}");

        return json_encode([
            'period_a' => $periodA,
            'period_b' => $periodB,
            'dimension' => $dimension,
            'filters' => $this->visibleFilters($filters),
            'total_net_sales_a' => $totalA,
            'total_net_sales_b' => $totalB,
            'total_change_rp' => $totalA - $totalB,
            'total_growth_persen' => $totalB > 0 ? round((($totalA - $totalB) / $totalB) * 100, 2) : null,
            'kontributor_kenaikan' => $topDrivers->toArray(),
            'kontributor_penurunan' => $bottomDrivers->toArray(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getSalesSummary(array $args): string
    {
        $period = $args['period'] ?? $this->getLatestPeriod();

        $row = Transaction::where('period', $period)
            ->selectRaw('
                SUM(CASE WHEN type = "I" THEN taxed_amt ELSE 0 END) as omset_kotor,
                SUM(CASE WHEN type = "R" THEN ABS(taxed_amt) ELSE 0 END) as total_retur,
                SUM(CASE WHEN type = "I" THEN cogs ELSE 0 END) as invoice_cogs,
                SUM(CASE WHEN type = "R" THEN ABS(cogs) ELSE 0 END) as return_cogs,
                SUM(CASE WHEN type = "I" THEN disc_total ELSE 0 END) as total_diskon,
                COUNT(CASE WHEN type = "I" THEN 1 END) as jumlah_invoice,
                COUNT(CASE WHEN type = "R" THEN 1 END) as jumlah_retur,
                COUNT(DISTINCT CASE WHEN type = "I" THEN outlet_id END) as toko_aktif,
                COUNT(DISTINCT CASE WHEN type = "I" THEN product_id END) as produk_aktif
            ')
            ->first();

        $omset = (float) ($row->omset_kotor ?? 0);
        $retur = (float) ($row->total_retur ?? 0);
        $netSales = $omset - $retur;
        $cogs = (float) ($row->invoice_cogs ?? 0) - (float) ($row->return_cogs ?? 0);
        $margin = $netSales > 0 ? round((($netSales - $cogs) / $netSales) * 100, 2) : 0;

        return json_encode([
            'periode' => $period,
            'omset_kotor' => $omset,
            'total_retur' => $retur,
            'penjualan_bersih' => $netSales,
            'total_cogs' => $cogs,
            'margin_kotor_persen' => $margin,
            'total_diskon' => (float) ($row->total_diskon ?? 0),
            'jumlah_invoice' => (int) ($row->jumlah_invoice ?? 0),
            'jumlah_retur' => (int) ($row->jumlah_retur ?? 0),
            'toko_aktif' => (int) ($row->toko_aktif ?? 0),
            'produk_aktif' => (int) ($row->produk_aktif ?? 0),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getTopEntities(array $args): string
    {
        $type = $args['entity_type'];
        $period = $args['period'] ?? $this->getLatestPeriod();
        $limit = min((int) ($args['limit'] ?? 5), 20);
        $order = ($args['order'] ?? 'top') === 'bottom' ? 'asc' : 'desc';

        $columnMap = [
            'product' => ['fk' => 'product_id', 'table' => 'products', 'name_col' => 'name'],
            'outlet' => ['fk' => 'outlet_id', 'table' => 'outlets', 'name_col' => 'name'],
            'salesman' => ['fk' => 'salesman_id', 'table' => 'salesmen', 'name_col' => 'name'],
        ];

        if (! isset($columnMap[$type])) {
            return json_encode(['error' => "Entity type '{$type}' tidak valid."]);
        }

        $cfg = $columnMap[$type];

        $results = Transaction::where('transactions.period', $period)
            ->join($cfg['table'], "transactions.{$cfg['fk']}", '=', "{$cfg['table']}.id")
            ->select(
                "{$cfg['table']}.{$cfg['name_col']} as nama",
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) as omset'),
                DB::raw('SUM(CASE WHEN transactions.type = "R" THEN ABS(transactions.taxed_amt) ELSE 0 END) as retur'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.qty_base ELSE 0 END) as total_qty'),
                DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.so_no END) as jumlah_transaksi')
            )
            ->groupBy("{$cfg['table']}.{$cfg['name_col']}")
            ->having('omset', '>', 0)
            ->orderBy('omset', $order)
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $net = (float) $r->omset - (float) $r->retur;

                return [
                    'nama' => $r->nama,
                    'omset_kotor' => (float) $r->omset,
                    'retur' => (float) $r->retur,
                    'net_sales' => $net,
                    'total_qty' => (int) $r->total_qty,
                    'jumlah_transaksi' => (int) $r->jumlah_transaksi,
                ];
            });

        return json_encode([
            'periode' => $period,
            'entity_type' => $type,
            'order' => $order === 'desc' ? 'top' : 'bottom',
            'count' => $results->count(),
            'data' => $results->toArray(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getEntityDetail(array $args): string
    {
        $type = $args['entity_type'];
        $name = $args['name'];
        $period = $args['period'] ?? $this->getLatestPeriod();

        $columnMap = [
            'product' => ['fk' => 'product_id', 'table' => 'products', 'name_col' => 'name'],
            'outlet' => ['fk' => 'outlet_id', 'table' => 'outlets', 'name_col' => 'name'],
            'salesman' => ['fk' => 'salesman_id', 'table' => 'salesmen', 'name_col' => 'name'],
        ];

        if (! isset($columnMap[$type])) {
            return json_encode(['error' => "Entity type '{$type}' tidak valid."]);
        }

        $cfg = $columnMap[$type];

        $results = Transaction::where('transactions.period', $period)
            ->join($cfg['table'], "transactions.{$cfg['fk']}", '=', "{$cfg['table']}.id")
            ->where("{$cfg['table']}.{$cfg['name_col']}", 'LIKE', "%{$name}%")
            ->select(
                "{$cfg['table']}.{$cfg['name_col']} as nama",
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) as omset'),
                DB::raw('SUM(CASE WHEN transactions.type = "R" THEN ABS(transactions.taxed_amt) ELSE 0 END) as retur'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs WHEN transactions.type = "R" THEN -ABS(transactions.cogs) ELSE 0 END) as cogs'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.disc_total ELSE 0 END) as diskon'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.qty_base ELSE 0 END) as total_qty'),
                DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.outlet_id END) as jumlah_toko'),
                DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.so_no END) as jumlah_invoice')
            )
            ->groupBy("{$cfg['table']}.{$cfg['name_col']}")
            ->get()
            ->map(function ($r) {
                $net = (float) $r->omset - (float) $r->retur;
                $cogs = (float) $r->cogs;
                $profit = $net - $cogs;
                $margin = $net > 0 ? round(($profit / $net) * 100, 2) : 0;

                return [
                    'nama' => $r->nama,
                    'omset_kotor' => (float) $r->omset,
                    'retur' => (float) $r->retur,
                    'net_sales' => $net,
                    'cogs' => $cogs,
                    'laba_kotor' => $profit,
                    'margin_persen' => $margin,
                    'total_diskon' => (float) $r->diskon,
                    'total_qty' => (int) $r->total_qty,
                    'jumlah_toko' => (int) $r->jumlah_toko,
                    'jumlah_invoice' => (int) $r->jumlah_invoice,
                ];
            });

        if ($results->isEmpty()) {
            return json_encode([
                'periode' => $period,
                'pesan' => "Tidak ditemukan data {$type} dengan nama mengandung '{$name}' di periode {$period}.",
            ], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'periode' => $period,
            'pencarian' => $name,
            'ditemukan' => $results->count(),
            'data' => $results->toArray(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getStockAlerts(array $args): string
    {
        $alertType = $args['alert_type'];
        $limit = min((int) ($args['limit'] ?? 10), 30);
        $period = SalesPerStock::max('period') ?? date('Y-m');

        $query = SalesPerStock::where('period', $period);

        if ($alertType === 'critical') {
            $query->where('swc', '<', 2)->where('swc', '>', 0)->orderBy('swc', 'asc');
        } elseif ($alertType === 'overstock') {
            $query->where('swc', '>', 12)->orderByDesc('stock_value_on_hand');
        } else {
            $query->orderByDesc('on_sales_base');
        }

        $results = $query->limit($limit)->get()->map(fn ($s) => [
            'nama_barang' => $s->item_name,
            'principal' => $s->principal_name,
            'stok_on_hand' => (int) $s->on_hand_base,
            'stok_on_sales' => (int) $s->on_sales_base,
            'swc_minggu' => (float) $s->swc,
            'nilai_stok' => (float) $s->stock_value_on_hand,
            'umur_barang' => $s->age_of_goods,
        ]);

        return json_encode([
            'periode_stok' => $period,
            'tipe_alert' => $alertType,
            'count' => $results->count(),
            'data' => $results->toArray(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getArReceivables(array $args): string
    {
        $filter = $args['filter'];
        $limit = min((int) ($args['limit'] ?? 10), 30);
        $search = $args['search'] ?? null;

        $log = ArImportLog::where('status', 'completed')->orderByDesc('report_date')->first();

        if (! $log) {
            return json_encode(['pesan' => 'Belum ada data piutang (AR) yang diimport ke sistem.'], JSON_UNESCAPED_UNICODE);
        }

        $query = ArReceivable::where('ar_import_log_id', $log->id)->where('ar_balance', '>', 0);

        if ($filter === 'overdue') {
            $query->where('overdue_days', '>', 0)->orderByDesc('overdue_days');
        } elseif ($filter === 'critical') {
            $query->where('overdue_days', '>', 60)->orderByDesc('ar_balance');
        } else {
            $query->orderByDesc('ar_balance');
        }

        if ($search) {
            $query->where('outlet_name', 'LIKE', "%{$search}%");
        }

        $results = $query->limit($limit)->get()->map(fn ($a) => [
            'toko' => $a->outlet_name,
            'salesman' => $a->salesman_name,
            'principal' => $a->principal_name,
            'sisa_piutang' => (float) $a->ar_balance,
            'telat_hari' => (int) $a->overdue_days,
            'tanggal_dokumen' => $a->doc_date,
            'tanggal_jatuh_tempo' => $a->due_date,
        ]);

        $totalAr = ArReceivable::where('ar_import_log_id', $log->id)->where('ar_balance', '>', 0)->sum('ar_balance');

        return json_encode([
            'tanggal_laporan' => $log->report_date,
            'filter' => $filter,
            'total_piutang_seluruhnya' => (float) $totalAr,
            'count' => $results->count(),
            'data' => $results->toArray(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function comparePeriods(array $args): string
    {
        $periodA = $args['period_a'];
        $periodB = $args['period_b'];

        $getSummary = function (string $p): array {
            $row = Transaction::where('period', $p)
                ->selectRaw('
                    SUM(CASE WHEN type = "I" THEN taxed_amt ELSE 0 END) as omset,
                    SUM(CASE WHEN type = "R" THEN ABS(taxed_amt) ELSE 0 END) as retur,
                    SUM(CASE WHEN type = "I" THEN cogs ELSE 0 END) - SUM(CASE WHEN type = "R" THEN ABS(cogs) ELSE 0 END) as cogs,
                    COUNT(DISTINCT CASE WHEN type = "I" THEN outlet_id END) as toko_aktif,
                    COUNT(DISTINCT CASE WHEN type = "I" THEN product_id END) as produk_aktif
                ')
                ->first();

            $omset = (float) ($row->omset ?? 0);
            $retur = (float) ($row->retur ?? 0);
            $net = $omset - $retur;
            $cogs = (float) ($row->cogs ?? 0);
            $margin = $net > 0 ? round((($net - $cogs) / $net) * 100, 2) : 0;

            return [
                'omset_kotor' => $omset,
                'retur' => $retur,
                'net_sales' => $net,
                'cogs' => $cogs,
                'margin_persen' => $margin,
                'toko_aktif' => (int) ($row->toko_aktif ?? 0),
                'produk_aktif' => (int) ($row->produk_aktif ?? 0),
            ];
        };

        $a = $getSummary($periodA);
        $b = $getSummary($periodB);

        $growth = [];
        foreach (['omset_kotor', 'retur', 'net_sales'] as $key) {
            $growth[$key.'_growth_persen'] = $b[$key] > 0 ? round((($a[$key] - $b[$key]) / $b[$key]) * 100, 2) : 0;
        }

        return json_encode([
            "periode_{$periodA}" => $a,
            "periode_{$periodB}" => $b,
            'pertumbuhan_a_vs_b' => $growth,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function getAvailablePeriods(): string
    {
        $periods = Transaction::select('period')
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period')
            ->toArray();

        return json_encode([
            'total_periode' => count($periods),
            'terbaru' => $periods[0] ?? null,
            'terlama' => end($periods) ?: null,
            'daftar' => $periods,
        ], JSON_UNESCAPED_UNICODE);
    }
}
