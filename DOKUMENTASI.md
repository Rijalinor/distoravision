# 🌌 DOKUMENTASI TEKNIS & OPERASIONAL SISTEM - DISTORAVISION

Dokumen ini merupakan panduan referensi tunggal (Master Documentation) untuk arsitektur, basis data, keamanan, algoritma analitik, dan panduan operasional platform **DistoraVision**.

---

## 📌 DAFTAR ISI
1. [Cover & Informasi Proyek](#1-cover--informasi-proyek)
2. [Version History (Riwayat Versi)](#2-version-history-riwayat-versi)
3. [Gambaran Umum Sistem](#3-gambaran-umum-sistem)
4. [Requirement Sistem (Kebutuhan Sistem)](#4-requirement-sistem-kebutuhan-sistem)
5. [Arsitektur Sistem](#5-arsitektur-sistem)
6. [User Roles & ACL (Hak Akses)](#6-user-roles--acl-hak-akses)
7. [Database Documentation (Dokumentasi Basis Data)](#7-database-documentation-dokumentasi-basis-data)
8. [Modul Documentation (Dokumentasi Modul Analitik)](#8-modul-documentation-dokumentasi-modul-analitik)
9. [API & AJAX Documentation](#9-api--ajax-documentation)
10. [Security Documentation (Dokumentasi Keamanan)](#10-security-documentation-dokumentasi-keamanan)
11. [Dashboard Documentation (Panduan Visual Dashboard)](#11-dashboard-documentation-panduan-visual-dashboard)
12. [Installation Guide (Panduan Instalasi)](#12-installation-guide-panduan-instalasi)
13. [User Manual (Panduan Pengguna)](#13-user-manual-panduan-pengguna)
14. [Testing Documentation (Dokumentasi Pengujian)](#14-testing-documentation-dokumentasi-pengujian)
15. [Backup & Recovery (Cadangan & Pemulihan)](#15-backup--recovery-cadangan--pemulihan)
16. [Maintenance Guide (Panduan Pemeliharaan)](#16-maintenance-guide-panduan-pemeliharaan)
17. [Lampiran (Kode Wilayah & Mapping Kolom)](#17-lampiran-kode-wilayah--mapping-kolom)

---

## 1. Cover & Informasi Proyek

*   **Nama Platform:** DistoraVision
*   **Tagline:** Executive Business Intelligence & Predictive Analytics Platform for Secondary Sales Distribution
*   **Pengembang:** Rijalinor & Tim
*   **Bahasa Pemrograman Utama:** PHP 8.2+ (Backend), JavaScript / AlpineJS (Frontend)
*   **Framework Utama:** Laravel 12.x
*   **Status Dokumen:** Rilis Resmi (Final)
*   **Tanggal Pembaruan Terakhir:** 17 Juni 2026

---

## 2. Version History (Riwayat Versi)

| Versi | Tanggal | Penulis | Deskripsi Perubahan |
| :--- | :--- | :--- | :--- |
| **v1.0.0** | 05 Juni 2026 | Rijalinor | Rilis Awal: Modul Secondary Sales, Profil Salesman, & Target Tracker. |
| **v1.1.0** | 06 Juni 2026 | Rijalinor | Integrasi Modul Peramalan Permintaan (Pure Demand Forecasting) berbasis WMA & YoY Seasonality. |
| **v1.2.0** | 08 Juni 2026 | Rijalinor | Rilis Modul Accounts Receivable (AR) Analytics, Import AR, dan integrasi penagihan kritis. |
| **v2.0.0** | 15 Juni 2026 | Rijalinor | Redesain Antarmuka Utama: Tema Sleek Navy (Navy Palette) & Peningkatan Readability Teks. |
| **v2.1.0** | 17 Juni 2026 | Antigravity | Konsolidasi & Rekonstruksi Master Dokumentasi 17 Bagian dari Awal. |

---

## 3. Gambaran Umum Sistem

**DistoraVision** adalah platform *Business Intelligence* (BI) kelas eksekutif yang dirancang khusus untuk menjembatani jurang pemisah antara data transaksi penjualan sekunder mentah dari distributor dengan keputusan strategis manajemen puncak (C-Suite). 

### Masalah yang Dipecahkan:
1.  **Overstock & Stockout:** Kesalahan estimasi pengadaan barang akibat fluktuasi musiman dan stok kosong historis (OOS).
2.  **Piutang Macet (Bad Debt):** Keterlambatan penagihan piutang toko yang tidak terdeteksi secara dini.
3.  **Target Salesman yang Tidak Adil:** Pembagian target global yang menyamaratakan seluruh area tanpa melihat performa historis.
4.  **Tingginya Retur Barang:** Lemahnya monitoring terhadap kontribusi retur per outlet/salesman.

### Solusi DistoraVision:
*   Menganalisis tren penjualan sekunder secara real-time.
*   Mengklasifikasikan siklus hidup produk (Product Trajectory) dengan analisis Slope regresi linier.
*   Memantau kualitas piutang outlet (AR Analytics) berdasarkan aging bucket, limit kredit, dan prioritas penagihan.
*   Meramalkan kebutuhan stok secara akurat lewat penyesuaian gap stok (OOS Imputation).
*   Mendistribusikan target secara proporsional berdasarkan kontribusi riil 3 bulan terakhir.

---

## 4. Requirement Sistem (Kebutuhan Sistem)

### A. Kebutuhan Server (Minimum)
*   **Operating System:** Windows Server 2019+ / Ubuntu 22.04 LTS
*   **Processor:** Intel Xeon / AMD EPYC (Minimal 2 Cores)
*   **RAM:** Minimal 4 GB (8 GB direkomendasikan untuk pemrosesan file Excel besar)
*   **Database Server:** MySQL 8.0+ / MariaDB 10.5+
*   **PHP Engine:** PHP 8.2+
    *   *Ekstensi PHP Wajib:* `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `gd`.
*   **Web Server:** Nginx / Apache HTTP Server
*   **Package Managers:** Composer v2.x & Node.js 18+ (NPM v10+)

### B. Kebutuhan Client (Browser)
*   Google Chrome (v100+)
*   Microsoft Edge (v100+)
*   Mozilla Firefox (v100+)
*   Safari (v15+)

---

## 5. Arsitektur Sistem

DistoraVision dibangun menggunakan pola arsitektur **MVC (Model-View-Controller)** yang disediakan oleh **Laravel 12.x** dengan struktur direktori baru yang dirampingkan (*streamlined file structure*).

```mermaid
graph TD
    User([Pengguna / Client Browser]) -->|HTTP Request| Route[Vite / Web Routes]
    Route -->|Controller Dispatch| Ctrl[Controllers]
    Ctrl -->|Query / Scope Scoping| Model[Eloquent Models]
    Model -->|Interact| DB[(MySQL Database)]
    Ctrl -->|Process Jobs Async| Queue[Queue Worker / Redis / Database Queue]
    Ctrl -->|Send Chat Context| Groq[Groq AI / Llama 3.1 LLM Service]
    Ctrl -->|Render| View[Blade views + Tailwind CSS + AlpineJS]
    View -->|Response HTML/JS| User
```

### Komponen Tech Stack:
1.  **Backend Core:** Laravel 12.x & PHP 8.2.
2.  **Frontend Styling:** Tailwind CSS v3 & AlpineJS v3 untuk manipulasi DOM responsif.
3.  **Visualisasi Data:** ApexCharts untuk chart interaktif di dashboard eksekutif dan AR.
4.  **AI Engine:** Groq API SDK (Model `llama-3.1-8b-instant`) dengan Tool Call untuk query basis data real-time secara aman.
5.  **Job Processing:** Laravel Queue System (menggunakan driver `database` atau `redis`) untuk mengimpor file Excel berukuran besar di latar belakang.

---

## 6. User Roles & ACL (Hak Akses)

DistoraVision menerapkan kontrol akses berbasis peran (*Role-Based Access Control*) dengan tingkatan otorisasi sebagai berikut:

### A. Matriks Hak Akses Modul

| Modul / Fitur | Administrator | Supervisor (SPV) | Salesman | Guest / Viewer |
| :--- | :---: | :---: | :---: | :---: |
| **Unggah Data Excel (Sales/AR)** | Ya | Tidak | Tidak | Tidak |
| **Hapus Batch Import** | Ya | Tidak | Tidak | Tidak |
| **Manajemen User & Hak Akses** | Ya | Tidak | Tidak | Tidak |
| **Tutup Buku (Period Management)** | Ya | Tidak | Tidak | Tidak |
| **Edit Target Salesman** | Ya | Tidak | Tidak | Tidak |
| **Dashboard Executive (Nasional)** | Ya | Tidak | Tidak | Tidak |
| **Dashboard Supervisor (Principal)** | Ya | Ya (Dibatasi Principal) | Tidak | Tidak |
| **My Dashboard (Salesman Personal)** | Ya | Ya | Ya (Milik Sendiri) | Tidak |
| **TV Dashboard Leaderboard** | Ya | Ya | Ya | Ya |
| **AI Chat Assistant** | Ya | Ya (Scoping Data) | Ya (Scoping Data) | Tidak |

### B. Mekanisme Pembatasan Data (Scoping Data)
Untuk menjamin keamanan informasi antar principal dan salesman, sistem menggunakan **Laravel Global Scopes**:
*   **Scoping Salesman:** Jika akun yang masuk memiliki peran `salesman` yang terhubung ke data `salesmen.id`, maka seluruh query ke tabel `transactions` dan `ar_receivables` otomatis disaring hanya untuk baris data yang mengandung `salesman_code` atau `salesman_id` milik user tersebut.
*   **Scoping Supervisor:** Jika akun masuk sebagai `supervisor`, query disaring secara otomatis menggunakan tabel relasi pivot `principal_user` sehingga supervisor hanya dapat melihat produk, penjualan, dan piutang dari principal binaannya.

---

## 7. Database Documentation (Dokumentasi Basis Data)

Berikut adalah kamus data untuk tabel-tabel inti dalam sistem DistoraVision.

```mermaid
erDiagram
    branches ||--o{ transactions : has
    salesmen ||--o{ transactions : makes
    outlets ||--o{ transactions : buys
    products ||--o{ transactions : included_in
    principals ||--o{ products : owns
    ar_import_logs ||--o{ ar_receivables : stores
    sales_per_import_logs ||--o{ sales_per_transactions : stores
    sales_per_import_logs ||--o{ sales_per_stocks : tracks
    accounting_periods ||--o{ closing_snapshots : records
```

### A. Kamus Data Tabel Utama

#### 1. Tabel: `transactions`
Menyimpan transaksi penjualan sekunder utama (Faktur & Retur).
*   `id` (BIGINT, Primary Key, Auto Increment)
*   `branch_id` (FOREIGN KEY -> `branches.id`, Cascade)
*   `salesman_id` (FOREIGN KEY -> `salesmen.id`, Cascade)
*   `outlet_id` (FOREIGN KEY -> `outlets.id`, Cascade)
*   `product_id` (FOREIGN KEY -> `products.id`, Cascade)
*   `type` (ENUM('I', 'R')): `I` untuk Invoice/Faktur, `R` untuk Return/Retur.
*   `so_no` (VARCHAR(100), Nullable): Nomor pesanan penjualan.
*   `so_date` (DATE, Nullable): Tanggal transaksi penjualan.
*   `qty_base` (INT): Jumlah barang dalam unit terkecil.
*   `taxed_amt` (DECIMAL(18,4)): Nilai rupiah transaksi setelah pajak.
*   `cogs` (DECIMAL(18,4)): Biaya Pokok Penjualan (COGS) barang terkait.
*   `period` (VARCHAR(7), Indexed): Format `YYYY-MM` (contoh: `2026-05`).

#### 2. Tabel: `ar_receivables`
Menyimpan piutang outstanding outlet yang diimpor dari modul AR.
*   `id` (BIGINT, Primary Key, Auto Increment)
*   `ar_import_log_id` (FOREIGN KEY -> `ar_import_logs.id`, Cascade)
*   `outlet_code` (VARCHAR(50), Indexed)
*   `outlet_name` (VARCHAR(255), Nullable)
*   `supervisor` (VARCHAR(255), Nullable)
*   `salesman_code` (VARCHAR(50), Indexed)
*   `salesman_name` (VARCHAR(255), Nullable)
*   `pfi_sn` (VARCHAR(100), Indexed): Kunci unik faktur piutang.
*   `doc_date` (DATE)
*   `due_date` (DATE)
*   `top` (SMALLINT): Terms of Payment (dalam hari).
*   `ar_amount` (DECIMAL(18,2)): Nilai awal tagihan piutang.
*   `ar_paid` (DECIMAL(18,2)): Jumlah nominal yang telah dibayarkan.
*   `ar_balance` (DECIMAL(18,2)): Sisa saldo piutang outstanding.
*   `credit_limit` (DECIMAL(18,2)): Limit kredit yang diberikan untuk outlet tersebut.
*   `cm` (TINYINT): Collection Mention (jumlah surat tagihan yang dicetak).
*   `overdue_days` (INT, Indexed): Jumlah hari keterlambatan penagihan.
*   `branch_sheet` (VARCHAR(50)): Nama cabang/sheet asal Excel (contoh: `BJM`).

#### 3. Tabel: `sales_per_stocks`
Menyimpan data stok persediaan fisik produk yang disinkronkan dengan kinerja mingguan.
*   `id` (BIGINT, Primary Key, Auto Increment)
*   `sales_per_import_log_id` (FOREIGN KEY -> `sales_per_import_logs.id`, Cascade)
*   `principal_name` (VARCHAR(255), Nullable)
*   `item_no` (VARCHAR(50), Indexed): Kode unik barang/SKU.
*   `item_name` (VARCHAR(255), Nullable)
*   `on_hand_base` (INT): Jumlah stok fisik di gudang saat ini.
*   `stock_value_on_hand` (DECIMAL(18,4)): Nilai rupiah persediaan di gudang.
*   `was` (DECIMAL(10,4)): Weekly Average Sales (Rata-rata penjualan mingguan).
*   `swc` (DECIMAL(8,2)): Stock Week Cover (daya dukung stok dalam satuan minggu).
*   `age_of_goods` (INT): Umur penyimpanan barang di gudang (hari).
*   `period` (VARCHAR(7), Indexed): Format `YYYY-MM`.

#### 4. Tabel: `closing_snapshots`
Menyimpan ringkasan metrik performa saat proses "Tutup Buku" bulanan dilakukan.
*   `id` (BIGINT, Primary Key, Auto Increment)
*   `accounting_period_id` (FOREIGN KEY -> `accounting_periods.id`, Cascade)
*   `total_sales` (DECIMAL(18,2))
*   `net_sales` (DECIMAL(18,2))
*   `total_outstanding` (DECIMAL(18,2))
*   `total_overdue` (DECIMAL(18,2))
*   `aging_data` (JSON, Nullable): Penyimpanan sebaran aging bucket outstanding.
*   `salesman_sales_data` (JSON, Nullable): Ringkasan pencapaian omset per salesman.
*   `salesman_ar_data` (JSON, Nullable): Ringkasan piutang per salesman.

---

## 8. Modul Documentation (Dokumentasi Modul Analitik)

### Modul 1: KPI Command Center (C-Suite Intelligence)
Menyediakan ringkasan eksekutif untuk Direktur/Owner mengenai profitabilitas dan pertumbuhan penjualan bulanan secara real-time.
*   **Net Sales:** `Omset Kotor - Total Retur`
*   **Gross Margin %:** `((Net Sales - Net COGS) / Net Sales) * 100`
*   **MoM Growth %:** `((Net Sales Bulan Ini - Net Sales Bulan Lalu) / Net Sales Bulan Lalu) * 100`

### Modul 2: Proportional Target Tracker
Sistem alokasi target penjualan global ke setiap salesman berdasarkan performa historis.
*   **Rasio Kontribusi:** `Omset Salesman i (3 Bulan Terakhir) / Total Omset Tim (3 Bulan Terakhir)`
*   **Target Baru Salesman:** `Rasio Kontribusi * Target Global Perusahaan`
*   **Pace Harian (Run Rate):** `(Target Baru - Omset MTD Berjalan) / Sisa Hari Kerja`

### Modul 3: Salesman 360° Appraisal
Profil analisis performa individu salesman. Menilai tingkat kunjungan efektif (strike rate), rasio pengembalian barang (return-to-sales ratio), serta efektivitas penagihan piutang (collection rate).

### Modul 4: Outlet & Principal Intelligence
*   **RFM Segmentation:** Mengelompokkan outlet secara dinamis ke dalam 4 kategori berdasarkan *Recency*, *Frequency*, dan *Monetary Value*:
    *   *Champions / Loyal:* Transaksi baru, sering, nilai belanja tinggi.
    *   *At Risk:* Lama tidak bertransaksi namun nilai transaksi historis tinggi.
    *   *Hibernating:* Jarang belanja, nilai transaksi kecil, dan sudah lama tidak aktif.
*   **Brand Affinity Mapping:** Menganalisis sebaran pembelian produk antar principal di setiap outlet guna menemukan potensi penjualan silang (*cross-selling*).

### Modul 5: AR Analytics (Accounts Receivable)
Memantau piutang outlet berdasarkan umur faktur jatuh tempo.
*   **Overdue Days:** `Tanggal Laporan - Tanggal Jatuh Tempo`
*   **Aging Buckets:** `Current (Belum Jatuh Tempo)`, `1-30 Hari`, `31-60 Hari`, `61-90 Hari`, `>90 Hari`.
*   **Collection Rate %:** `(Total Bayar Piutang / Total Nilai Piutang Awal) * 100`

### Modul 6: Sales vs Stock Analytics (Stock Week Cover)
Menganalisis tingkat kesehatan ketersediaan stok fisik gudang terhadap rata-rata penjualan mingguan (WAS).
*   **Formula SWC:** `Stok Gudang / WAS (Rata-rata Penjualan Mingguan)`
*   **Status Kritis:** `SWC <= 2` (Persediaan terancam kosong).
*   **Status Dead Stock:** `SWC > 12` atau Penjualan produk = 0 dalam periode berjalan.

### Modul 7: Restock Predictor
Menghitung siklus belanja rata-rata untuk memproyeksikan tanggal transaksi berikutnya bagi tiap outlet.
*   **Siklus Hari:** Rata-rata selisih hari antar pesanan dari toko yang sama.
*   **Estimasi Tanggal Restock:** `Tanggal Transaksi Terakhir + Rata-Rata Siklus Hari`

### Modul 8: Predictive Demand Forecasting
Mesin proyeksi kebutuhan produk berbasis data deret waktu (Time Series) dengan 4 langkah perhitungan utama:
1.  **Imputasi OOS:** Baseline penjualan dinaikkan otomatis untuk mengompensasi hari-hari barang kosong.
2.  **Base WMA:** Perhitungan Moving Average berbobot 3 bulan: `(T-1 * 50%) + (T-2 * 30%) + (T-3 * 20%)`.
3.  **Seasonality YoY:** Rasio penjualan bulan target tahun lalu dibagi rata-rata penjualan 12 bulan tahun lalu.
4.  **Final Forecast:** `Base WMA * Seasonality YoY` (Dibulatkan ke atas).

### Modul 9: AI Chat & Insight Generator
Integrasi asisten virtual Distora AI berbasis Groq API (`llama-3.1-8b-instant`). Dilengkapi dengan pustaka instruksi `AiToolsService` untuk mengambil data penjualan, tren 6 bulan, status stok, dan piutang bermasalah secara langsung lewat *SQL Tool Call* tanpa membocorkan database secara mentah.

### Modul 10: TV Wallboard Dashboard
Tampilan layar monitor otomatis (*Wallboard*) di kantor cabang. Berfungsi menampilkan performa harian tim, *running text* pengumuman penting, dan leaderboard nilai piutang outstanding per salesman secara berulang.

---

## 9. API & AJAX Documentation

Aplikasi ini menggunakan perutean monolitik dengan beberapa endpoint AJAX yang merespons dalam format JSON untuk kebutuhan pembaruan antarmuka secara dinamis.

### A. Endpoint: AI Chat Response
*   **URL:** `/ai-chat/ask`
*   **Method:** `POST`
*   **Headers:** `Content-Type: application/json`, `X-CSRF-TOKEN`
*   **Payload Request:**
    ```json
    {
      "history": [
        {"role": "user", "content": "Berapa net sales bulan Mei 2026?"}
      ],
      "model": "llama-3.1-8b-instant"
    }
    ```
*   **Format Respons Sukses (Status 200):**
    ```json
    {
      "reply": "Net sales untuk periode Mei 2026 adalah Rp 4.567.890.000 dengan laba kotor sebesar Rp 890.000.000 (Margin 19.5%).\n\nPendapat Distora AI: Performa penjualan stabil namun perhatikan peningkatan retur sebesar 2% MoM.",
      "remaining_tokens": "78200"
    }
    ```

### B. Endpoint: Dynamic Stock Tabs Loader
*   **URL:** `/sales-per/stock/tab-kritis` | `/sales-per/stock/tab-tertahan` | `/sales-per/stock/tab-semua`
*   **Method:** `GET`
*   **Query Parameters:**
    *   `period` (format `YYYY-MM`, opsional)
    *   `principal` (nama principal, opsional)
*   **Format Respons:** HTML parsial (Blade Rendered View) berisi baris tabel persediaan yang dimuat secara asinkron (*lazy load*) oleh AlpineJS untuk mempercepat waktu pemuatan halaman awal.

---

## 10. Security Documentation (Dokumentasi Keamanan)

### A. Proteksi Sistem Autentikasi & Sesi
*   **Laravel Breeze:** Autentikasi kredensial pengguna dilindungi oleh enkripsi hashing bcrypt.
*   **Session Lifetime:** Sesi tidak aktif disetel otomatis kedaluwarsa setelah 120 menit (konfigurasi `config/session.php`).

### B. Pertahanan Terhadap Kerentanan Web (OWASP Top 10)
1.  **SQL Injection:** Seluruh query basis data menggunakan **Eloquent ORM** dan **PDO Parameter Binding** yang secara otomatis mengamankan input dari eksekusi perintah SQL berbahaya.
2.  **Cross-Site Scripting (XSS):** Blade Templating Engine menggunakan sintaks double kurung kurawal `{{ $variable }}` yang menyaring tag HTML berbahaya secara otomatis sebelum ditampilkan di browser.
3.  **Cross-Site Request Forgery (CSRF):** Setiap request bertipe `POST`, `PUT`, atau `DELETE` wajib melampirkan token CSRF unik yang dihasilkan sistem. Jika token tidak cocok, sistem mengembalikan error status `419 Page Expired`.
4.  **API Rate Limiting:** Endpoint pengiriman AI Chat dibatasi maksimal 10 permintaan per menit (`throttle:10,1`) guna menghindari serangan spamming (DDoS) dan kelebihan kuota Groq API.

---

## 11. Dashboard Documentation (Panduan Visual Dashboard)

### A. Executive Dashboard
*   **Widget Utama:** Kartu Ringkasan (Net Sales, Gross Margin %, MoM Growth %) berlatar belakang gradasi modern Sleek Navy.
*   **AI Insight Banner:** Menampilkan kesimpulan analisis performa bisnis yang dihasilkan asisten AI secara otomatis saat halaman dibuka.
*   **Pareto Chart & RFM Grid:** Diagram batang interaktif ApexCharts yang memetakan outlet kategori A dan principal dengan performa terbaik.

### B. AR Dashboard (Accounts Receivable)
Terdiri dari 8 Tab Navigasi Dinamis:
1.  *Ringkasan (Overview):* KPI Outstanding global, jumlah outlet piutang, dan ringkasan nilai giro berjalan.
2.  *Aging Bucket:* Grafik diagram lingkaran sebaran piutang berdasarkan kelompok umur (Current, 1-30, dll).
3.  *Credit Risk:* Daftar toko dengan utilisasi limit kredit kritis (>80%).
4.  *Top Outlets:* Urutan toko dengan nilai piutang outstanding terbesar.
5.  *Payment Status:* Klasifikasi perilaku pembayaran outlet (Lunas, Sebagian, Macet).
6.  *Salesman AR:* Rekapitulasi sisa piutang dan rata-rata hari overdue per salesman.
7.  *Giro:* Daftar giro aktif yang belum jatuh tempo beserta bank penerbitnya.
8.  *Detail:* Tabel pencarian faktur piutang berbasis teks lengkap.

---

## 12. Installation Guide (Panduan Instalasi)

Ikuti langkah-langkah di bawah ini untuk memasang sistem DistoraVision di server lokal maupun produksi:

### 1. Persiapan Berkas
Ekstrak repositori atau clone dari Git:
```bash
git clone https://github.com/Rijalinor/distoravision.git
cd distoravision
```

### 2. Instalasi Dependensi PHP & JavaScript
```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

### 3. Konfigurasi Lingkungan (`.env`)
Salin berkas template lingkungan dan buat kunci enkripsi aplikasi:
```bash
cp .env.example .env
php artisan key:generate
```
Edit berkas `.env` untuk menyesuaikan koneksi MySQL dan Groq API Key:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=distoravision_db
DB_USERNAME=root
DB_PASSWORD=rahasia

GROQ_API_KEY=gsk_xxxxxxxxxxxxxxxxxxxx
```

### 4. Migrasi & Seeding Awal Basis Data
Jalankan migrasi tabel-tabel utama:
```bash
php artisan migrate --force
```

### 5. Konfigurasi Queue Worker (Latar Belakang)
Jalankan worker di server agar sistem dapat memproses file import Excel:
```bash
php artisan queue:work --queue=default --sleep=3 --tries=3
```
*(Direkomendasikan menggunakan aplikasi manager seperti **Supervisor** di Linux untuk menjaga agar queue worker terus berjalan).*

---

### 💡 Panduan Mengaktifkan Demo Mode (Untuk Uji Coba)
DistoraVision dilengkapi dengan mesin simulasi data untuk demonstrasi tanpa membutuhkan file penjualan riil.

1.  **Generate Data Demo:** Jalankan perintah seeder untuk membuat data transaksi fiktif pada periode tertentu (format `YYYY-MM`):
    ```bash
    php artisan demo:seed 2026-05 --rows=1500
    ```
2.  **Aktifkan di UI:** Klik tombol **"Demo Mode"** yang terletak di bilah navigasi atas (Header) atau menu samping (Sidebar). Seluruh visualisasi dashboard akan otomatis membaca data simulasi yang baru dibuat.

---

## 13. User Manual (Panduan Pengguna)

### A. Panduan Bagi Administrator (Admin)

#### 1. Cara Mengunggah Data Transaksi Penjualan Bulanan
1.  Masuk ke menu **Import Sales** di menu samping.
2.  Klik tombol **Upload File**.
3.  Pilih Periode Transaksi (contoh: `2026-05`), pilih file Excel, lalu klik **Proses**.
4.  Sistem akan memasukkan tugas ke antrean background job. Anda dapat memantau statusnya di halaman riwayat import (`Pending` -> `Processing` -> `Completed`/`Failed`).

#### 2. Cara Melakukan Tutup Buku Bulanan
> [!IMPORTANT]
> Tutup Buku berfungsi untuk menyalin dan "membekukan" data KPI bulan berjalan ke tabel historis `closing_snapshots` agar dapat dianalisis kembali di masa mendatang tanpa takut perubahan data masa lalu.
1.  Masuk ke menu **Tutup Buku / Periods**.
2.  Cari periode bulan berjalan (contoh: `2026-05`).
3.  Klik tombol **Tutup Buku / Close Period**.
4.  Sistem akan merekam seluruh visualisasi, nominal penjualan, dan status piutang terakhir. Status periode akan berubah menjadi `Closed` dan data pada bulan tersebut tidak dapat diubah kembali kecuali admin mengklik **Buka Kembali / Reopen Period**.

---

### B. Panduan Bagi Salesman
1.  Masuk menggunakan akun salesman Anda.
2.  Anda akan diarahkan langsung ke **My Dashboard**.
3.  Di sini Anda dapat melihat pencapaian target MTD berjalan, sisa target yang harus dikejar, *Pace Harian* (Run Rate) penjualan Anda, dan daftar toko binaan yang memiliki piutang jatuh tempo kritis (>60 hari atau CM >= 3) untuk ditindaklanjuti.

---

## 14. Testing Documentation (Dokumentasi Pengujian)

DistoraVision menggunakan framework pengujian **PHPUnit** untuk memastikan fungsionalitas logika analitik, rumus perhitungan, dan pembatasan data (ACL) berjalan dengan benar.

### A. Cara Menjalankan Pengujian
Jalankan perintah berikut di terminal repositori proyek:
```bash
# Menjalankan seluruh suite pengujian secara ringkas
php artisan test --compact

# Menjalankan pengujian khusus pada satu file spesifik
php artisan test --compact tests/Feature/ArImportTest.php
```

### B. Cakupan Pengujian (Test Coverage)
*   **Feature Tests:** Pengujian alur otentikasi login, pengunggahan berkas Excel sales/AR, validasi guard periode tutup buku, simulasi AI Chat, dan verifikasi alokasi pembagian target salesman.
*   **Unit Tests:** Pengujian ekstraksi rumus konversi karton produk, normalisasi kalkulasi hari keterlambatan piutang (overdue), dan pembulatan forecast.

---

## 15. Backup & Recovery (Cadangan & Pemulihan)

Untuk mengantisipasi kegagalan server atau kerusakan data, lakukan pencadangan rutin sesuai prosedur berikut:

### A. Prosedur Backup Otomatis
1.  **Backup Basis Data (MySQL):** Jalankan perintah `mysqldump` untuk mengekspor database ke file SQL terkompresi.
    ```bash
    mysqldump -u root -p distoravision_db | gzip > storage/backups/db_backup_$(date +%F).sql.gz
    ```
2.  **Backup Berkas Unggahan (Excel Logs):** Salin direktori penyimpanan file mentah hasil unggahan.
    ```bash
    tar -czf storage/backups/files_backup_$(date +%F).tar.gz storage/app/public/imports/
    ```

### B. Prosedur Recovery (Pemulihan Data)
Jika server mengalami kerusakan total dan perlu dipulihkan di server baru:
1.  Siapkan database kosong dengan nama yang sama di MySQL.
2.  Ekstrak file backup database:
    ```bash
    gunzip < db_backup_xxxx-xx-xx.sql.gz | mysql -u root -p distoravision_db
    ```
3.  Ekstrak berkas log unggahan ke direktori `storage/app/public/`.

---

## 16. Maintenance Guide (Panduan Pemeliharaan)

Lakukan pemeliharaan rutin berikut agar server DistoraVision tetap memiliki performa optimal:

### A. Pembersihan Log Riwayat Import
File Excel yang diunggah menumpuk di server seiring waktu. Untuk membersihkan log gagal atau file usang yang berumur lebih dari 90 hari, jalankan perintah pembersihan storage:
```bash
php artisan storage:link
# Jalankan cron job berkala untuk membersihkan direktori tmp/
```

### B. Standardisasi Gaya Penulisan Kode (Laravel Pint)
Sebelum melakukan commit perubahan kode PHP di server repositori, jalankan formatter **Laravel Pint** agar kode tetap bersih dan seragam:
```bash
vendor/bin/pint --dirty --format agent
```

### C. Pembaruan Dependensi Sistem
```bash
# Update library php composer
composer update --no-dev

# Update modul Javascript npm
npm update
npm run build
```

---

## 17. Lampiran (Kode Wilayah & Mapping Kolom)

### A. Kode Wilayah Toko Terdaftar (Cabang Kalimantan/Selatan)
```text
AHD, AIR, AML, ANJ, ANT, AUN, AYN, BAI, BJB, BJR, BLG, BLR, BNJ, BPP, BRB, BRL, BRM, BRP, BRS, 
BRU, BTH, BTL, BTM, CAS, CKT, CMR, CNR, DPR, GMB, HRM, IKK, IKN, INP, JHH, JTI, KAU, KLA, KLD, 
KLP, KPG, KPS, KRP, KRU, KTB, LDU, LKS, LMA, LML, MAK, MNR, MRB, MRN, MRP, MTP, OBM, ODM, PBN, 
PHB, PKR, PKU, PLH, PLJ, PLK, PMT, PND, PSM, PST, SDN, SHB, SKR, SMY, SNG, SPD, SPT, STB, TAI, 
TBN, THR, TJG, TLD, TLT, TLW, TPD, TRP, UKA, WKG, WLD, WPC.
```

### B. Pemetaan Kolom File Excel AR (Import AR Mapping)
Berikut adalah konfigurasi pemetaan kolom default yang disematkan dalam berkas `config/import_columns.php`:
*   `ar_pfi_sn` -> `'pfisn'` / `'pfi_sn'` (Kunci Invoice Piutang)
*   `ar_outlet_id` -> `'outlet_id'` / `'outlet_code'` (Kode Unik Toko)
*   `ar_ar_balance` -> `'ar_balance'` / `'outstanding'` (Sisa Saldo Piutang)
*   `ar_overdue_days` -> `'over_due'` / `'overdue'` (Hari Keterlambatan)
*   `ar_credit_limit` -> `'credit_limit'` / `'plafon_kredit'` (Limit Kredit Outlet)
*   `ar_cm` -> `'cm'` / `'collection_mention'` (Status Cetak Tagihan)

---
*Dokumentasi Master ini disusun secara terperinci untuk memenuhi kebutuhan audit teknis dan panduan operasional pengguna DistoraVision.*
