# Dokumentasi Lengkap Sistem Analitik DistoraVision & Implementasi Excel

Dokumen ini berisi panduan cara kerja dan rumus matematika (logika algoritma) untuk setiap fitur analitik yang ada di dalam sistem DistoraVision. Dilengkapi juga dengan panduan **Implementasi di Excel**, kolom data mentah apa saja yang dibutuhkan, dan rumus fungsi Excel yang bisa Anda gunakan.

---

## 1. Predictive Demand Forecasting (Peramalan Penjualan)
Mencegah *Out of Stock* (OOS) maupun *Overstock* dengan memprediksi kebutuhan stok di masa depan.

### A. Rumus Sistem (Algoritma)
* **Imputasi OOS (Run Rate):** `Imputed Qty = (Total Qty T-1 / Total Hari Terjual T-1) × Rata-Rata Hari Terjual Historis`
* **WMA:** `WMA = (Bulan T-1 × 50%) + (Bulan T-2 × 30%) + (Bulan T-3 × 20%)`
* **Seasonality Index:** `Index = Penjualan Bulan Target (Tahun Lalu) / Rata-rata Penjualan 12 Bulan`
* **Final Forecast:** `WMA × Seasonality Index` (dibulatkan ke atas).

### B. Implementasi di Excel
* **Kolom Mentah yang Dibutuhkan:** `Bulan/Tanggal Transaksi`, `Kode Produk`, `Qty Terjual`, `Nama Toko`.
* **Langkah & Rumus Excel:**
  1. Buat **Pivot Table** dengan *Rows* `Kode Produk` dan *Columns* `Bulan/Tanggal`. Masukkan *Values* berupa `Sum of Qty Terjual`.
  2. Untuk **WMA**, jika penjualan bulan ini di sel `D2`, bulan lalu `C2`, dan dua bulan lalu `B2`:
     `=(D2*0.5) + (C2*0.3) + (B2*0.2)`
  3. Untuk menghitung **Hari Aktif Jualan** (Imputasi), Anda butuh kolom bantuan yang menghitung jumlah hari beda transaksi menggunakan `=COUNTIFS()`.

---

## 2. Product Trajectory Analysis (Siklus Hidup Produk)
Mengklasifikasikan masa depan setiap produk (Growing, Declining, Dead).

### A. Rumus Sistem (Algoritma)
* Sistem menghitung kemiringan (Slope) garis trend penjualan 6 bulan terakhir.
* **% Slope:** `(Slope / Rata-rata Penjualan 6 Bulan) × 100`
* **Growing:** `% Slope > 10%`, **Declining:** `% Slope < -10%`.

### B. Implementasi di Excel
* **Kolom Mentah yang Dibutuhkan:** `Bulan` (dalam angka 1, 2, 3, dst), `Kode Produk`, `Total Penjualan Rupiah/Qty`.
* **Langkah & Rumus Excel:**
  1. Siapkan data historis berurutan selama 6 bulan terakhir. Misal, bulan 1-6 ada di range `B1:G1` dan total sales produk A ada di range `B2:G2`.
  2. Gunakan fungsi **SLOPE**:
     `=SLOPE(B2:G2, B1:G1)`
  3. Hitung rata-rata 6 bulan dengan `=AVERAGE(B2:G2)`.
  4. Hitung `% Slope` menggunakan: `=(Hasil_Slope / Hasil_Average) * 100`.
  5. Kategorikan menggunakan **IF**:
     `=IF(H2>10, "Growing", IF(H2<-10, "Declining", "Stable"))`

---

## 3. Advanced Analytics (Pareto & Basket)
Melihat fokus produk unggulan dan kebiasaan belanja silang (Cross-selling).

### A. Rumus Sistem (Algoritma)
* **Pareto Kontribusi:** `(Total Sales Produk / Total Revenue) × 100`
* **Basket Afinitas:** `(Jumlah Toko Beli Produk Utama & Terkait / Total Toko Beli Produk Utama) × 100`

### B. Implementasi di Excel
* **Kolom Mentah Pareto:** `Kode Produk`, `Total Omset`.
* **Langkah Pareto di Excel:**
  1. Urutkan data berdasarkan `Total Omset` secara *Descending* (Z to A).
  2. Buat kolom `% Kontribusi` di sel `C2`: `=B2/SUM($B$2:$B$1000)*100`.
  3. Buat kolom `% Kumulatif` di sel `D2`: `=SUM($C$2:C2)`.
  4. Kategorikan Kelas dengan IF bersarang: `=IF(D2<=80, "A", IF(D2<=95, "B", "C"))`.
* **Kolom Mentah Basket Analysis:** `ID Transaksi`, `Nama Toko`, `Kode Produk`.
* **Langkah Basket di Excel:**
  Membutuhkan proses matriks yang sulit dilakukan langsung dengan rumus biasa. Pendekatan Excel terbaik adalah menggunakan **Power Pivot** atau melakukan *Self-Join* di Power Query untuk menghitung irisan (toko mana yang ada di Produk A dan Produk B sekaligus).

---

## 4. Main Dashboard KPIs (Metrik Eksekutif)
Melihat kesehatan fundamental secara menyeluruh.

### A. Rumus Sistem (Algoritma)
* **Net Sales:** `Omset Faktur - Omset Retur`
* **Gross Margin:** `((Net Sales - Net COGS) / Net Sales) × 100`
* **MoM Growth:** `((Bulan Ini - Bulan Lalu) / Bulan Lalu) × 100`

### B. Implementasi di Excel
* **Kolom Mentah yang Dibutuhkan:** `Bulan/Tanggal`, `Tipe Dokumen (Faktur/Retur)`, `Nilai Barang (Rp)`, `Nilai COGS (Rp)`.
* **Langkah & Rumus Excel:**
  1. Hitung Net Sales dengan **SUMIFS**:
     `=SUMIFS(NilaiBarang_Range, Tipe_Range, "Faktur") - SUMIFS(NilaiBarang_Range, Tipe_Range, "Retur")`
  2. Hitung Margin Laba: `= (Net_Sales - Total_COGS) / Net_Sales` (Format sel sebagai Persentase `%`).
  3. Hitung MoM: `=(Sales_Nov - Sales_Okt) / Sales_Okt`.

---

## 5. AR Analytics & Evaluasi Penagihan Piutang
Melacak piutang macet dan keterlambatan pembayaran.

### A. Rumus Sistem (Algoritma)
* **Overdue Days:** `Tanggal Hari Ini - Tanggal Jatuh Tempo (Due Date)`
* **Prioritas Penindakan:** `Overdue > 60 Hari` ATAU `Credit Margin (CM) >= 3`

### B. Implementasi di Excel
* **Kolom Mentah yang Dibutuhkan:** `Nama Toko`, `No Invoice`, `Tanggal Jatuh Tempo`, `Sisa Tagihan / Saldo Piutang`, `CM (Jumlah Meleset Janji)`.
* **Langkah & Rumus Excel:**
  1. Hitung Umur Tunggakan (DSO) di sel `E2`:
     `=TODAY() - C2`
  2. Buat Aging Bucket menggunakan fungsi IFS atau VLOOKUP *Approximate Match*:
     `=IFS(E2<=0, "Current", AND(E2>=1, E2<=30), "1-30", AND(E2>=31, E2<=60), "31-60", E2>60, ">60")`
  3. Beri penanda Tagihan Kritis:
     `=IF(OR(E2>60, CM_Cell>=3), "Tindak Tegas!", "Aman")`. Tambahkan *Conditional Formatting* warna merah jika hasilnya "Tindak Tegas!".

---

## 6. Sales vs Stock Analytics (Alokasi Modal)
Mencegah uang mati karena *Dead Stock*.

### A. Rumus Sistem (Algoritma)
* **SWC (Stock Week Cover):** `Total Qty On Hand / WAS (Rata-rata Penjualan Mingguan)`
* **Stok Kritis:** `SWC <= 2`
* **Stok Mati:** `SWC > 8`

### B. Implementasi di Excel
* **Kolom Mentah yang Dibutuhkan:** `Kode Produk`, `Sisa Stok Fisik (Qty)`, `Nilai Rupiah Stok`, `Penjualan Rata-rata Mingguan (WAS)`.
* **Langkah & Rumus Excel:**
  1. Dapatkan `WAS` dengan cara mengambil total jualan sebulan dibagi 4.
  2. Hitung **SWC** di sel `E2`: `=B2 / D2` (Qty On Hand dibagi WAS). Jika hasil error `DIV/0!` (karena jualan = 0), bungkus dengan `IFERROR(B2/D2, 999)`.
  3. Beri Status:
     `=IFS(E2=999, "Dead Stock (0 Sales)", E2<=2, "Kritis", E2>8, "Slow Moving", TRUE, "Sehat")`
  4. Hitung Kesehatan Modal: Jumlahkan `Nilai Rupiah Stok` yang statusnya "Sehat", lalu bagi dengan `Total Nilai Seluruh Stok` di gudang. Jika hasilnya >80%, gudang Anda sehat.

---

## 7. Restock Predictor (Pola Siklus Belanja Toko)
Menghitung siklus belanja tiap toko untuk mengetahui kapan mereka akan habis barang.

### A. Rumus Sistem (Algoritma)
* **Siklus Hari:** `Rata-rata selisih hari antar order di satu toko`.
* **Prediksi Tanggal Order Berikutnya:** `Tanggal Order Terakhir + Siklus Hari`.

### B. Implementasi di Excel
* **Kolom Mentah yang Dibutuhkan:** `Nama Toko`, `Kode Produk`, `Tanggal Pembelian`.
* **Langkah & Rumus Excel:**
  1. Urutkan data berdasarkan `Nama Toko` lalu `Kode Produk` lalu `Tanggal Pembelian` secara **A to Z** (Terlama ke Terbaru).
  2. Buat kolom bantuan "Selisih Hari" di baris 3. Gunakan IF untuk mengecek apakah toko dan produknya masih sama dengan baris sebelumnya:
     `=IF(AND(A3=A2, B3=B2), C3-C2, "")`
  3. Buat **Pivot Table** dengan *Rows* `Nama Toko` dan `Kode Produk`. Masukkan *Values* berupa `Average of Selisih Hari`. Ini adalah siklus belanja rata-ratanya.
  4. Tambahkan `Max of Tanggal Pembelian` di Pivot untuk tahu tanggal beli terakhir, lalu tambahkan rumus `=Tanggal_Terakhir + Rata_rata_Siklus` untuk menebak tanggal beli berikutnya.

---

## 8. Target Tracker & Performa Salesman
Mendistribusikan target penjualan global ke masing-masing orang secara rasional.

### A. Rumus Sistem (Algoritma)
* **Rasio Historis:** `Omset Salesman 3 Bln Terakhir / Omset Total Tim 3 Bln Terakhir`
* **Target Baru:** `Rasio Historis × Target Global Perusahaan`
* **Pace Harian (Run Rate):** `(Target - Pencapaian Saat Ini) / Sisa Hari Kerja`

### B. Implementasi di Excel
* **Kolom Mentah yang Dibutuhkan:** `Nama Salesman`, `Omset Q1 (Historis)`, `Penjualan Berjalan (MTD)`.
* **Langkah & Rumus Excel:**
  1. Hitung Total Omset Tim di sel bawah (misal `B10` = `=SUM(B2:B9)`).
  2. Hitung % Kontribusi Tiap Sales: `=B2/$B$10`.
  3. Hitung Rekomendasi Target. Misal target perusahaan bulan ini adalah 10 Milyar (di sel `G1`): `=C2 * $G$1`.
  4. Hitung Sisa Hari Kerja (misal menggunakan networkdays): `=NETWORKDAYS(TODAY(), EOMONTH(TODAY(),0))`.
  5. Hitung Run Rate: `=(Target_Salesman - Penjualan_Berjalan) / Sisa_Hari_Kerja`.
  6. Evaluasi Performa %: `=Penjualan_Berjalan / Target_Salesman * 100`. Warnai merah jika masih di bawah 80%.
