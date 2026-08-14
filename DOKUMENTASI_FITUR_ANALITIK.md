# Dokumentasi Fitur Analitik dan Alur Kerja DistoraVision

Dokumen ini menjelaskan tujuan setiap fitur, sumber data, rumus hitungan, dan alur kerja operasional DistoraVision. Fokusnya adalah menjawab: angka ini dihitung dari mana, dipakai untuk keputusan apa, dan bagaimana user sebaiknya membaca dashboard.

Terakhir diperbarui: 14 Agustus 2026

---

## 1. Ringkasan Aplikasi

DistoraVision adalah aplikasi Business Intelligence untuk distributor. Sistem mengubah data transaksi penjualan, retur, stok gudang, target salesman, dan piutang menjadi dashboard analitik.

Tujuan utama aplikasi:

- Memantau performa penjualan dan margin.
- Menemukan produk, outlet, principal, dan salesman yang paling berdampak.
- Mendeteksi risiko retur, stok kosong, stok macet, dan piutang macet.
- Membantu perencanaan target, restock, dan demand forecast.
- Memberi jawaban cepat lewat AI Chat berbasis data internal.

---

## 2. Sumber Data Utama

### 2.1 `transactions`

Data transaksi secondary sales utama.

Dipakai oleh:

- Dashboard eksekutif.
- Pareto analysis.
- Margin analysis.
- Salesman profitability.
- Target tracker.
- Cohort analysis.
- Cross-selling.
- Outlet trajectory.
- Product trajectory.
- Forecasting.
- AI Chat.

Kolom penting:

| Kolom | Arti |
| --- | --- |
| `type` | `I` = invoice, `R` = retur |
| `taxed_amt` | Nilai transaksi setelah pajak |
| `gross` | Nilai bruto sebelum diskon |
| `disc_total` | Total diskon |
| `cogs` | Harga pokok penjualan |
| `qty_base` | Kuantitas dalam satuan dasar |
| `period` | Periode `YYYY-MM` |
| `so_date` | Tanggal dokumen transaksi |
| `salesman_id` | Salesman yang menangani transaksi |
| `outlet_id` | Outlet pembeli |
| `product_id` | Produk/SKU |

### 2.2 `sales_per_transactions`

Data transaksi dari file Sales Per.

Dipakai oleh:

- Sales Per dashboard.
- Leaderboard salesman dari file Sales Per.
- Drill-down salesman Sales Per.

### 2.3 `sales_per_stocks`

Data stok gudang dari file Sales Per.

Dipakai oleh:

- Analisis stok gudang.
- Stock Week Cover.
- Stok kritis.
- Slow moving dan modal tertahan.
- Fast moving stock.

Kolom penting:

| Kolom | Arti |
| --- | --- |
| `on_hand_base` | Jumlah stok fisik |
| `stock_value_on_hand` | Nilai rupiah stok |
| `was` | Weekly Average Sales |
| `swc` | Stock Week Cover |
| `age_of_goods` | Umur barang |
| `warehouse_name` | Nama gudang |

### 2.4 `ar_receivables`

Data piutang outstanding dari file AR.

Dipakai oleh:

- AR dashboard.
- Aging piutang.
- Prioritas penagihan.
- Giro dan invoice monitoring.
- Evaluasi salesman/outlet dari sisi piutang.

Kolom penting:

| Kolom | Arti |
| --- | --- |
| `ar_amount` | Nilai awal tagihan |
| `ar_paid` | Nilai sudah dibayar |
| `ar_balance` | Sisa outstanding |
| `overdue_days` | Hari keterlambatan |
| `due_date` | Tanggal jatuh tempo |
| `credit_limit` | Limit kredit outlet |
| `cm` | Collection Mention |

### 2.5 `salesman_targets`

Data target salesman per periode.

Dipakai oleh:

- Dashboard eksekutif target global.
- Target tracker.
- Progress target per salesman.

---

## 3. Definisi Metrik Standar

### 3.1 Sales dan Retur

| Metrik | Rumus |
| --- | --- |
| Gross Invoice Sales | `SUM(taxed_amt)` untuk transaksi `type = I` |
| Total Returns | `SUM(ABS(taxed_amt))` untuk transaksi `type = R` |
| Net Sales | `Gross Invoice Sales - Total Returns` |
| Return Rate | `(Total Returns / Gross Invoice Sales) * 100` |

Catatan:

- Pareto, margin, report summary, dan trajectory memakai net sales agar retur tidak membuat kontribusi terlihat terlalu tinggi.
- Ranking operasional tertentu tetap menampilkan gross invoice jika tujuannya melihat volume invoice.

### 3.2 COGS dan Margin

| Metrik | Rumus |
| --- | --- |
| Invoice COGS | `SUM(cogs)` untuk invoice |
| Return COGS | `SUM(ABS(cogs))` untuk retur |
| Net COGS | `Invoice COGS - Return COGS` |
| Gross Profit | `Net Sales - Net COGS` |
| Margin % | `(Gross Profit / Net Sales) * 100` |

### 3.3 Diskon

| Metrik | Rumus |
| --- | --- |
| Discount Depth | `(Total Discount / Total Gross) * 100` |

Diskon dihitung dari invoice karena retur bukan aktivitas pemberian diskon.

### 3.4 Target

| Metrik | Rumus |
| --- | --- |
| Progress Target | `(Sales MTD / Target) * 100` |
| Shortfall | `MAX(Target - Sales MTD, 0)` |
| Required Run Rate | `Shortfall / Sisa Hari Kerja` |

Jika target global belum diset, dashboard menampilkan `Target belum diset` dan tidak memakai angka dummy.

---

## 4. Hak Akses dan Scoping Data

### Admin

Admin dapat mengimpor data, menghapus batch import, melihat semua dashboard, mengatur user, menutup/membuka periode buku, dan mengatur target salesman.

### Supervisor

Supervisor hanya melihat data sesuai principal yang ditugaskan. Query berbasis `transactions` mengikuti global scope model `Transaction`. Restock predictor yang memakai raw SQL juga dibatasi ke principal milik supervisor.

### Salesman

Salesman hanya melihat data dirinya sendiri pada fitur yang dibuka untuk salesman, misalnya My Dashboard dan AI Chat scoped. Salesman diblokir dari fitur analitik manajemen seperti margin, Pareto, cohort, restock predictor, forecasting inventory, dan dashboard Sales Per/stock.

---

## 5. Alur Kerja Data End-to-End

### Import Sales

1. Admin membuka menu Import Sales.
2. Admin memilih file Excel/CSV dan periode.
3. Sistem membuat import log.
4. Job background membaca file.
5. Data dinormalisasi ke branch, salesman, outlet, principal, product.
6. Baris transaksi masuk ke `transactions`.
7. Dashboard membaca periode terbaru atau periode yang dipilih user.

### Import AR

1. Admin membuka menu Import AR.
2. Admin mengunggah file AR dengan tanggal laporan.
3. Sistem membuat `ar_import_logs`.
4. Job background membaca invoice/piutang.
5. Data masuk ke `ar_receivables`.
6. AR dashboard memakai import AR completed terbaru berdasarkan `report_date`.

### Import Sales Per

1. Admin membuka menu Import Sales Per.
2. Admin memilih file dan periode.
3. Sistem membaca sheet penjualan, retur, dan stok gudang.
4. Transaksi masuk ke `sales_per_transactions`.
5. Stok masuk ke `sales_per_stocks`.
6. Sales Per dashboard dan Stock dashboard membaca data tersebut.

### Tutup Buku

1. Admin memastikan data sales, AR, dan stok periode sudah lengkap.
2. Admin membuka menu Periods.
3. Admin menutup periode.
4. Sistem menyimpan snapshot KPI ke `closing_snapshots`.
5. Periode ditandai closed.
6. Sistem membuat periode berikutnya jika diperlukan.

---

## 6. Dashboard Eksekutif

Controller: `DashboardController`

Tujuan:

- Ringkasan cepat performa perusahaan/principal.
- Alert stok, piutang, target, top produk, top outlet, dan breakdown principal.

Filter:

- `period`
- `start_period`
- `end_period`
- `principal_id`

Jika tidak ada filter periode, sistem default ke periode transaksi terbaru.

KPI:

| KPI | Rumus |
| --- | --- |
| Total Sales | `SUM(taxed_amt)` invoice |
| Total Returns | `SUM(ABS(taxed_amt))` retur |
| Net Sales | `Total Sales - Total Returns` |
| Net COGS | `Invoice COGS - Return COGS` |
| Margin | `((Net Sales - Net COGS) / Net Sales) * 100` |
| Return Rate | `(Total Returns / Total Sales) * 100` |
| MoM Net Sales | `((Net Sales Current - Net Sales Previous) / Net Sales Previous) * 100` |

Perbandingan periode:

- Jika user memilih range, sistem membandingkan dengan range sebelumnya yang durasinya sama.
- Contoh range Maret-Mei 2026 dibandingkan dengan Desember 2025-Februari 2026.

Alert:

| Alert | Logika |
| --- | --- |
| Stok kritis | `swc <= 2` dan `swc > 0` |
| Overstock | `swc >= 12` |
| Piutang jatuh tempo | `ar_balance > 0` dan `overdue_days > 0` dari AR completed terbaru |

Target global:

`Global Target = SUM(salesman_targets.target_amount)` untuk periode aktif.

Jika target ada:

`Global Progress = Net Sales / Global Target * 100`

Jika target tidak ada:

- Label target: `Target belum diset`.
- Progress: `-`.
- Progress bar: 0%.

---

## 7. Sales Per Dashboard

Controller: `SalesPerAnalyticsController`

Tujuan:

- Melihat performa dari file Sales Per.
- Membandingkan sales, retur, net sales, jumlah nota, jumlah outlet, dan salesman aktif.

KPI:

| KPI | Rumus |
| --- | --- |
| Overall Sales | `SUM(subtotal)` invoice |
| Overall Returns | `SUM(subtotal)` retur |
| Overall Net Sales | `Overall Sales - Overall Returns` |
| Return Rate | `(Overall Returns / Overall Sales) * 100` |
| Nota Count | `COUNT(DISTINCT so_no)` invoice |
| Outlet Count | `COUNT(DISTINCT outlet_code)` invoice |
| Active Salesmen | `COUNT(DISTINCT sales_code)` invoice |

Leaderboard salesman:

| Metrik | Rumus |
| --- | --- |
| Total Sales | `SUM(subtotal)` invoice |
| Total Returns | `SUM(ABS(subtotal))` retur |
| Net Sales | `Total Sales - Total Returns` |
| Return Rate | `Total Returns / Total Sales * 100` |
| Nota Count | `COUNT(DISTINCT so_no)` invoice |
| Outlet Count | `COUNT(DISTINCT outlet_code)` invoice |

Drill-down salesman menampilkan top produk dan top outlet dari invoice salesman terpilih.

---

## 8. Analisis Stok Gudang

Controller: `SalesPerStockController`

Tujuan:

- Mengetahui stok kritis, stok macet, modal tertahan, dan produk fast moving.

KPI:

| KPI | Rumus |
| --- | --- |
| Total SKU | `COUNT(*)` item stok pada periode/filter |
| Total Stock Value | `SUM(stock_value_on_hand)` |
| Total On Hand | `SUM(on_hand_base)` |
| Average SWC | `AVG(swc)` untuk `swc > 0` |
| Critical Low | `COUNT(swc <= 2 AND swc > 0)` |
| Slow Moving | `COUNT((swc > 8 OR swc = 0) AND stock_value_on_hand > 0)` |

SWC konseptual:

`SWC = Stok On Hand / Weekly Average Sales`

Interpretasi SWC:

| Status | Kondisi |
| --- | --- |
| No Sales | `swc = 0` |
| Kritis | `1 <= swc <= 2` |
| Aman pendek | `3 <= swc <= 4` |
| Normal | `5 <= swc <= 8` |
| Tertahan | `swc > 8` |
| Overstock berat | `swc > 12` |

Pareto capital allocation:

| Metrik | Rumus |
| --- | --- |
| Fast Value | nilai stok dengan `0 < swc <= 8` |
| Slow Value | nilai stok dengan `swc > 8` atau `swc = 0` |
| Fast % | `Fast Value / Total Stock Value * 100` |

Jika `Fast % >= 80`, alokasi modal stok dianggap sehat.

---

## 9. AR Dashboard dan Aging Piutang

Controller: `ArAnalyticsController`

Tujuan:

- Memantau piutang outstanding.
- Mengelompokkan keterlambatan.
- Menentukan prioritas penagihan.
- Melihat giro dan invoice aktif.

Dashboard memakai `ar_import_logs` completed terbaru berdasarkan `report_date`.

Filter:

- Cabang/sheet.
- Tanggal dokumen.
- Salesman.
- Principal.
- Search outlet/invoice.

KPI global:

| KPI | Rumus |
| --- | --- |
| Total Outstanding | `SUM(ar_balance)` |
| Total AR Amount | `SUM(ar_amount)` |
| Total AR Paid | `SUM(ar_paid)` |
| Total Overdue | `SUM(ar_balance)` untuk `overdue_days > 0` dan `ar_balance > 0` |
| Outlet Count | `COUNT(DISTINCT outlet_code)` untuk `ar_balance > 0` |
| Invoice Count | `COUNT(*)` untuk `ar_balance > 0` |
| Avg Overdue | `AVG(overdue_days)` untuk invoice overdue aktif |
| Max Overdue | `MAX(overdue_days)` |
| Over Limit Count | count saat `credit_limit > 0 AND ar_balance > credit_limit` |
| Stubborn Count | count saat `cm >= 3 AND ar_balance > 0` |

Aging bucket:

| Bucket | Kondisi |
| --- | --- |
| Current | `overdue_days <= 0` |
| 1-30 | `overdue_days BETWEEN 1 AND 30` |
| 31-60 | `overdue_days BETWEEN 31 AND 60` |
| 61-90 | `overdue_days BETWEEN 61 AND 90` |
| >90 | `overdue_days > 90` |

Prioritas penindakan:

- `ar_balance > 0`, dan
- `overdue_days > 60` atau `cm >= 3`.

Risk outlet:

| Status | Kondisi |
| --- | --- |
| Kritis | `avg_overdue_days > 60` |
| Waspada | `avg_overdue_days > 30` |
| Normal | `avg_overdue_days <= 30` |

Catatan istilah:

- Tab ini memakai `overdue_days`, bukan DSO finansial penuh.
- Karena itu labelnya adalah Aging Piutang / Rata-rata Overdue.

---

## 10. Pareto Analysis

Controller: `ParetoController`

Tujuan:

- Menentukan produk atau outlet yang menyumbang kontribusi penjualan terbesar.
- Membantu fokus stok, promosi, kunjungan, dan pengamanan top account.

Mode:

| Type | Entity |
| --- | --- |
| `product` | Produk/SKU |
| `outlet` | Outlet/toko |

Rumus:

`Entity Sales = SUM(invoice taxed_amt) - SUM(return taxed_amt)`

`Contribution % = Entity Sales / Total Entity Sales * 100`

`Cumulative % = Akumulasi contribution dari ranking tertinggi ke terendah`

Kelas:

| Kelas | Kondisi |
| --- | --- |
| A | cumulative `<= 80%` |
| B | cumulative `> 80%` dan `<= 95%` |
| C | cumulative `> 95%` |

Interpretasi:

- Kelas A: aset utama yang harus dijaga stok dan relasinya.
- Kelas B: kandidat pengembangan.
- Kelas C: long tail yang perlu dievaluasi efisiensinya.

---

## 11. Margin Analysis

Controller: `MarginAnalysisController`

Tujuan:

- Melihat laba kotor secara global, principal, dan produk.
- Menemukan produk yang sales-nya besar tapi margin rendah.

Rumus:

| Metrik | Rumus |
| --- | --- |
| Revenue | `SUM(invoice taxed_amt) - SUM(return taxed_amt)` |
| COGS | `SUM(invoice cogs) - SUM(return cogs)` |
| Gross Profit | `Revenue - COGS` |
| Margin % | `Gross Profit / Revenue * 100` |

Output:

- Blended margin global.
- Total revenue.
- Total COGS.
- Total gross profit.
- Margin per principal.
- Top product margin berdasarkan gross profit.

---

## 12. Salesman Profitability

Controller: `SalesmanProfitabilityController`

Tujuan:

- Menilai salesman dari laba, bukan hanya omset.
- Mengidentifikasi salesman yang revenue-nya besar tetapi laba tidak sebanding.

Rumus per salesman:

| Metrik | Rumus |
| --- | --- |
| Gross Sales | `SUM(taxed_amt)` invoice |
| Total Returns | `SUM(ABS(taxed_amt))` retur |
| Net Sales | `Gross Sales - Total Returns` |
| Net COGS | `Invoice COGS - Return COGS` |
| Gross Profit | `Net Sales - Net COGS` |
| Margin % | `Gross Profit / Net Sales * 100` |
| Discount Depth | `Total Discount / Total Gross * 100` |
| Return Rate | `Total Returns / Gross Sales * 100` |
| Avg per Outlet | `Net Sales / Outlet Count` |
| Avg per Invoice | `Net Sales / Invoice Count` |

Efficiency ratio:

`Revenue Contribution = Net Sales Salesman / Total Net Sales`

`Profit Contribution = Gross Profit Salesman / Total Gross Profit`

`Efficiency Ratio = Profit Contribution / Revenue Contribution`

Interpretasi:

| Ratio | Arti |
| --- | --- |
| `> 1.0` | Laba lebih besar dari porsi revenue |
| `0.8 - 1.0` | Relatif sehat |
| `< 0.8` | Revenue besar tapi laba tidak sebanding |

---

## 13. Target Tracker

Controller: `TargetTrackerController`

Tujuan:

- Membagi target tim ke salesman secara proporsional.
- Melihat progress target berjalan.
- Menghitung sisa gap dan run rate harian.

Prioritas target:

1. Jika ada target tersimpan di `salesman_targets`, gunakan angka tersebut.
2. Jika belum ada, sistem memberi rekomendasi target dari kontribusi historis.

Kontribusi historis:

- Lookback: 3 bulan sebelum periode aktif.
- `Historical Revenue Salesman = SUM(taxed_amt invoice)` selama 3 bulan historis.
- `Contribution Ratio = Historical Revenue Salesman / Total Historical Revenue Team`.
- `Recommended Target = Contribution Ratio * Team Target`.

Progress:

| Metrik | Rumus |
| --- | --- |
| Sales MTD | `SUM(taxed_amt invoice)` periode aktif |
| Progress | `Sales MTD / Target * 100` |
| Shortfall | `MAX(Target - Sales MTD, 0)` |
| Required Run Rate | `Shortfall / Remaining Working Days` |

Asumsi hari kerja: 26 hari per bulan.

---

## 14. Cohort Analysis

Controller: `CohortAnalysisController`

Tujuan:

- Melihat retensi outlet berdasarkan bulan transaksi pertama.
- Mengukur apakah outlet yang didapat pada bulan tertentu tetap aktif pada bulan berikutnya.

Definisi:

`cohort_month = MIN(period)` dari invoice outlet.

Matrix:

- Jika outlet punya invoice pada period tertentu, outlet dihitung aktif.
- Retur saja tidak dihitung sebagai aktivitas cohort.

Isi matrix:

`Jumlah outlet dari cohort tertentu yang aktif pada period tertentu`

Filter:

- `period`
- `start_period`
- `end_period`
- `principal_id`

---

## 15. Cross-Selling / Basket Analysis

Controller: `CrossSellingController`

Tujuan:

- Menemukan produk yang sering dibeli oleh outlet yang sama.
- Menjadi dasar bundling, penetrasi produk, dan cross-selling.

Definisi basket:

- Basket adalah kumpulan produk unik yang dibeli satu outlet dalam periode/filter aktif.
- Sistem memakai transaksi invoice dan distinct produk per outlet.

Affinity:

`Affinity % = Jumlah outlet yang beli A dan B / Jumlah outlet yang beli A * 100`

Produk sumber hanya dipakai jika muncul di minimal 3 outlet.

---

## 16. Restock Predictor

Controller: `RestockPredictorController`

Tujuan:

- Mendeteksi pola beli ulang outlet per produk.
- Memperkirakan kapan outlet kemungkinan perlu order lagi.

Data:

- Lookback 6 bulan terakhir dari tanggal saat ini.
- Hanya invoice dari `transactions`.

Rumus:

| Metrik | Rumus |
| --- | --- |
| Avg Cycle Days | `AVG(DATEDIFF(so_date, prev_date))` |
| Avg Qty per Order | `AVG(daily_qty)` |
| Last Purchase Date | `MAX(so_date)` |
| Next Purchase Date | `Last Purchase Date + ROUND(Avg Cycle Days)` |

Alur:

1. Kelompokkan transaksi per outlet, produk, dan tanggal.
2. Hitung tanggal pembelian sebelumnya dengan window function `LAG`.
3. Hitung selisih hari antar pembelian.
4. Ambil rata-rata selisih hari.
5. Tambahkan rata-rata siklus ke tanggal pembelian terakhir.

Filter:

- Principal.
- Search outlet/product.

---

## 17. Forecasting Inventory

Controller: `ForecastingController`

Tujuan:

- Memproyeksikan demand produk untuk bulan berikutnya atau 6 bulan ke depan.
- Membantu pembelian dan suplai stok.

Data historis:

- Sampai 13 bulan dari periode pilihan.
- `total_qty = SUM(invoice qty_base) - SUM(return qty_base)`.
- `active_outlets = COUNT(DISTINCT outlet_id)` hanya dari invoice.
- `days_sold = COUNT(DISTINCT so_date)` hanya dari invoice.

Konversi karton:

- Sistem membaca pola seperti `(1x12x10)` dari nama produk.
- Faktor konversi = `1 * 12 * 10 = 120`.
- Output forecast dibagi faktor ini agar tampil dalam karton/CTN.

Koreksi stockout:

Jika:

- `avgDaysSold > 10`
- `daysSoldT1 > 0`
- `daysSoldT1 < avgDaysSold * 0.6`

Maka:

`runRate = qtyT1 / daysSoldT1`

`imputedQtyT1 = runRate * avgDaysSold`

Koreksi drop besar:

Jika:

- `outletsT1 < avgOutlets * 0.4`
- `qtyT1 < avgQty * 0.5`
- `avgOutlets > 0`

Maka sistem memakai `qtyT2` sebagai pengganti `T1`.

Koreksi promo spike:

Jika:

- `qtyT1 > avgQty * 1.5`
- `avgQty > 10`

Maka:

`usedT1 = avgQty * 1.5`

WMA:

`WMA = usedT1 * 0.5 + qtyT2 * 0.3 + qtyT3 * 0.2`

Seasonality:

`seasonalIndex = qtyTargetMonthLastYear / lastYearAvg`

Batas seasonal index:

- Minimum `0.5`.
- Maksimum `2.0`.

Final forecast single period:

`Final Forecast = CEIL(WMA * seasonalIndex)`

Multi-period forecast:

1. Hitung WMA.
2. Hitung slope 6 bulan terakhir.
3. Ubah slope menjadi monthly growth rate.
4. Batasi growth rate antara `-10%` sampai `+10%`.
5. Proyeksikan base setiap bulan secara compound.
6. Kalikan seasonal index masing-masing bulan.

`currentBase = currentBase * (1 + monthlyGrowthRate)`

`projectedQty = CEIL(currentBase * seasonalIndex)`

---

## 18. Outlet Trajectory

Controller: `OutletTrajectoryController`

Tujuan:

- Mengelompokkan outlet berdasarkan arah tren penjualan 6 bulan.
- Menemukan outlet declining sebelum benar-benar mati.

Data:

- Lookback 6 bulan sampai periode aktif.
- `net_sales = invoice taxed_amt - return taxed_amt`.
- Bulan tanpa transaksi diisi 0.

Slope regresi linier:

- X = index bulan `0..5`.
- Y = net sales bulanan.

`slope = ((n * SUM(xy)) - (SUM(x) * SUM(y))) / ((n * SUM(x^2)) - (SUM(x)^2))`

`slopePct = slope / avgSales * 100`

`avgSales = totalSales / jumlah bulan aktif`

Klasifikasi:

| Kelas | Kondisi |
| --- | --- |
| New | aktif hanya <= 1 bulan dan latest sales > 0 |
| Dead | aktif <= 1 bulan dan latest sales <= 0, atau latest dan previous sales sama-sama 0 |
| Growing | `slopePct > 10` |
| Declining | `slopePct < -10` |
| Stable | selain kondisi di atas |

Sorting default:

1. Declining.
2. Dead.
3. New.
4. Stable.
5. Growing.
6. Total sales terbesar.

---

## 19. Product Trajectory

Controller: `ProductTrajectoryController`

Tujuan:

- Mengelompokkan SKU berdasarkan arah tren penjualan 6 bulan.
- Membantu keputusan PO, stop order, clearance, dan pengamanan stok.

Logika sama dengan Outlet Trajectory, tetapi entity-nya produk/SKU.

Metrik:

`net_sales = invoice taxed_amt - return taxed_amt`

Klasifikasi:

- New.
- Dead.
- Growing.
- Declining.
- Stable.

Rekomendasi umum:

- Growing: pastikan stok aman.
- Declining: tahan pembelian baru, cek penyebab turun.
- Dead: evaluasi clearance/bundling.

---

## 20. Report / Buku Rapor 360

Controller: `ReportController`

Tujuan:

- Menggabungkan beberapa analisis penting dalam satu halaman/laporan.
- Bisa diekspor ke Excel.

Isi utama:

- KPI basic dan profitability.
- Top products.
- Sleeping outlets.
- Margin per principal.
- Top salesman profitability.
- Outlet trajectory summary.
- Pareto class summary.
- Promo uplift summary.

Sleeping outlets:

- Outlet aktif di periode sebelumnya.
- Tidak aktif atau net sales <= 0 di periode aktif.

`Sleeping Outlet Loss = SUM(prev_sales outlet yang hilang)`

Promo uplift:

`discount_pct = total_discount / total_gross * 100`

Produk dianggap punya pembanding promo jika:

- Minimal 2 periode data.
- Selisih discount percentage minimal 3 poin.
- Qty baseline dan promo masing-masing minimal 10.

Profit:

`profit = (total_gross - total_discount) - total_cogs`

Promo berhasil jika:

`profitPromo - profitNormal > 0`

---

## 21. AI Chat

Controller: `AiChatController`

Service:

- `GroqChatService`
- `AiToolsService`
- `AiContextService`

Tujuan:

- User bertanya dengan bahasa natural.
- AI memilih tool query yang sesuai.
- Sistem mengambil angka dari database.
- AI menjawab dengan narasi berbasis angka tersebut.

Alur:

1. User membuka AI Chat.
2. User mengirim pertanyaan.
3. Controller memvalidasi input dan rate limit.
4. `GroqChatService` membuat system prompt.
5. Model meminta tool jika butuh data.
6. `AiToolsService` menjalankan query sesuai tool.
7. Hasil tool dikirim kembali ke model.
8. Model menyusun jawaban final.

Prinsip anti-halusinasi:

- Hanya memakai angka dari hasil tool.
- Tidak mengarang data.
- Rekomendasi harus berbasis data.
- Jika data kurang, AI harus menyatakan data belum cukup.

Keamanan:

- Endpoint dibatasi throttle `10 request / menit`.
- Input history dibatasi.
- Role user memengaruhi scope data.
- Query tool tidak membuka akses SQL bebas ke user.

---

## 22. TV Dashboard

Controller: `TvDashboardController`

Tujuan:

- Menampilkan ringkasan performa di layar kantor.
- Auto-refresh untuk monitoring cepat.

Isi umum:

- MTD performance.
- Top principal.
- Top outlet.
- Ringkasan AR.
- Leaderboard atau performa salesman sesuai data tersedia.

Metrik yang dipakai mengikuti definisi dashboard utama dan AR dashboard.

---

## 23. Export CSV/Excel

Modul yang menyediakan export:

- Pareto.
- Cross-selling.
- Target tracker.
- Margin.
- Salesman profitability.
- Outlet trajectory.
- Product trajectory.
- Forecasting.
- Restock predictor.
- Report/Buku Rapor Excel.

Prinsip export:

- Data export mengikuti filter yang sedang dipilih.
- CSV memakai helper `CsvExportable`.
- Report Excel memakai `BukuRaporExport`.

---

## 24. Panduan Baca Dashboard

### Untuk Owner/Manajemen

1. Dashboard eksekutif: cek net sales, margin, return rate, MoM.
2. Alert: cek stok kritis, overstock, overdue AR.
3. Pareto: cek ketergantungan pada top produk/outlet.
4. Margin: cek apakah sales menghasilkan laba.
5. Salesman profitability: cek efisiensi tim.
6. Report 360: cek sleeping outlet, trajectory, promo.

### Untuk Supervisor

1. Filter principal yang ditangani.
2. Cek produk kelas A dan stoknya.
3. Cek outlet declining.
4. Cek AR prioritas.
5. Cek restock predictor untuk rencana kunjungan.

### Untuk Admin

1. Import sales.
2. Import AR.
3. Import Sales Per.
4. Cek import log sampai completed.
5. Validasi dashboard utama.
6. Simpan target jika sudah disepakati.
7. Tutup buku jika periode selesai.

### Untuk Salesman

1. My Dashboard.
2. Target/progress pribadi.
3. Outlet aktif dan piutang.
4. AI Chat untuk pertanyaan scoped ke data sendiri.

---

## 25. Catatan Konsistensi Analitik

Prinsip yang dipakai:

- Pareto dan report summary memakai net sales agar retur tidak membuat kontribusi terlihat terlalu tinggi.
- Cohort hanya menghitung invoice aktif, bukan retur.
- Forecast active outlet dan days sold hanya dari invoice.
- Aging piutang tidak disebut DSO karena rumusnya berbasis `overdue_days`.
- Target global kosong tidak diganti angka dummy.
- Raw SQL restock predictor tetap dibatasi principal untuk supervisor.

---

## 26. Batasan Analisis

1. Forecast bukan angka pasti, tetapi proyeksi berbasis pola historis.
2. Seasonality butuh data tahun sebelumnya; jika data tidak cukup, seasonal index default ke 1.0.
3. SWC bergantung pada kualitas WAS dari file Sales Per.
4. Aging piutang bergantung pada file AR terbaru yang statusnya completed.
5. Cohort lebih bermakna jika data minimal 2-3 bulan.
6. Cross-selling lebih kuat jika jumlah outlet dan basket cukup banyak.
7. Margin akurat jika kolom COGS dalam file sumber valid.

