# Dokumentasi Modul AR (Accounts Receivable / Piutang)

Dokumen ini menjelaskan seluruh fungsi dan fitur yang terkait AR (piutang) di DistoraVision, termasuk menu utama AR dan integrasi AR di modul lain.

## 1. Tujuan Modul AR

Modul AR dipakai untuk:
- memuat data piutang outlet dari file Excel,
- memonitor kualitas piutang (outstanding, overdue, collection rate, credit risk),
- menentukan prioritas penagihan per outlet/salesman,
- menyimpan snapshot AR saat proses tutup buku.

## 2. Cakupan Fitur AR di Sistem

Fitur AR tersebar di beberapa area:
- `Import AR` (`/ar/imports`, admin only)
- `Dashboard AR` (`/ar/dashboard`, semua user login, data tetap dibatasi ACL)
- `Salesman Profile` (section Kondisi Piutang/AR)
- `My Dashboard` untuk role salesman (ringkasan AR pribadi + invoice kritis)
- `Tutup Buku / Period Snapshot` (AR snapshot per periode)
- `TV Dashboard` (leaderboard menampilkan nilai piutang per salesman)

## 3. Hak Akses

- Admin:
  - bisa upload/import AR,
  - bisa hapus batch import AR,
  - bisa melihat dashboard AR.
- Supervisor:
  - bisa melihat dashboard AR, tapi data otomatis dibatasi principal yang dia pegang.
- Salesman:
  - bisa melihat dashboard AR, tapi data otomatis dibatasi sesuai salesman login.

Catatan: pembatasan data ini dilakukan oleh global scope di model `ArReceivable`.

## 4. Alur Kerja AR End-to-End

1. Admin upload file AR (tanggal laporan + nama sheet/cabang + file Excel).
2. Sistem membuat log import dengan status `pending`.
3. Job background memproses file, baca sheet terpilih, lalu simpan ke `ar_receivables`.
4. Status import berubah menjadi `processing` lalu `completed`/`failed`.
5. Dashboard AR selalu membaca dari **import completed paling terbaru** (berdasarkan `report_date`).
6. Data AR yang sama dipakai ulang di modul salesman, TV dashboard, dan snapshot tutup buku.

## 5. Fitur Import AR (Menu: Import AR)

### 5.1 Input dan Validasi

Form import berisi:
- `Tanggal Laporan AR` (wajib, date)
- `Pilih Sheet (Cabang)` (wajib, text, max 50 karakter)
- `File Excel AR` (wajib; format `xlsx`, `xls`, `csv`, `txt`; maksimal 50 MB)

### 5.2 Guard Periode Tutup Buku

Sebelum import dijalankan, sistem cek apakah periode dari `report_date` sudah ditutup (`AccountingPeriod::isPeriodClosed`).
- Jika periode sudah `closed`, import diblokir.
- User akan mendapat pesan bahwa periode sudah ditutup dan perlu dibuka kembali oleh admin.

### 5.3 Mekanisme Pembacaan Sheet

- User mengisi nama sheet (contoh: `BJM`).
- Sistem cari sheet Excel dengan **partial match** (misal input `BJM` bisa match `BJM (8-4-26)`).
- Jika sheet tidak ditemukan, status import jadi gagal dan error dicatat.

### 5.4 Riwayat Import AR

Halaman riwayat menampilkan:
- waktu import,
- nama file,
- tanggal laporan,
- sheet,
- total/sukses/gagal baris,
- status (`pending`, `processing`, `completed`, `failed`),
- tombol lihat error (jika ada),
- tombol hapus batch import.

### 5.5 Hapus Batch Import

Saat batch dihapus:
- semua data `ar_receivables` yang terkait ikut terhapus,
- log aktivitas dicatat ke activity log.

## 6. Proses Normalisasi Data Saat Import

Saat parsing per baris, sistem menjalankan beberapa logika agar data AR konsisten:

- `PFI/SN` wajib terisi (jika kosong, baris dianggap gagal).
- Jika `principal_code` kosong diisi `UNKNOWN`.
- Jika `principal_name` kosong diisi `UNKNOWN PRINCIPAL`.
- Jika `supervisor` kosong diisi `UNASSIGNED`.
- Tanggal Excel serial number dikonversi otomatis ke format `Y-m-d`.
- Jika `due_date` kosong tapi ada `doc_date` dan `TOP`, due date dihitung otomatis (`doc_date + TOP hari`).
- Jika `overdue_days` <= 0 tapi `due_date` sudah lewat dari `report_date`, overdue dihitung ulang otomatis.
- Nilai numerik (amount/balance) dibersihkan dari format string lalu diparse ke angka.

## 7. Dashboard AR (Menu: Dashboard AR)

## 7.1 Sumber Data Dashboard

Dashboard AR menggunakan **satu sumber aktif**, yaitu import AR dengan status `completed` dan `report_date` terbaru.

Jika belum ada data import, dashboard menampilkan empty state dan tombol import.

## 7.2 Filter Global

Filter global tersedia lintas tab:
- tanggal invoice (`start_date` - `end_date`)
- cabang/sheet (`branch`)
- salesman
- principal

Preset tanggal cepat:
- Hari Ini
- Bulan Ini
- Bulan Lalu
- 3 Bulan
- Tahun Ini

## 7.3 KPI Utama (Muncul di Semua Tab)

1. `Total Outstanding`
   - total nilai `ar_balance`.
2. `Total Overdue`
   - total `ar_balance` yang overdue (`overdue_days > 0`).
3. `Outlet Berpiutang`
   - jumlah outlet unik dengan `ar_balance > 0`.
4. `Rata-rata Overdue`
   - rata-rata hari overdue dari invoice overdue.
5. `Collection Rate`
   - `total_ar_paid / total_ar_amount x 100%`.
6. `Outlet Bandel`
   - jumlah invoice dengan `CM >= 3` dan masih outstanding.

Tambahan KPI internal:
- total invoice outstanding,
- jumlah outlet over limit kredit (`ar_balance > credit_limit`).

## 7.4 Tab dan Fungsinya

### a) Ringkasan (`overview`)
- Halaman pengantar navigasi.
- Menampilkan ringkasan giro (`total_giros`, `total_giro_amount`).

### b) Aging (`aging`)
- Distribusi piutang per bucket:
  - `Current`
  - `1-30`
  - `31-60`
  - `61-90`
  - `>90`
- Ada chart dan kartu bucket.
- Klik bucket menampilkan detail invoice sesuai kategori.

### c) Credit Risk (`credit-risk`)
- Hitung utilisasi limit kredit per outlet:
  - `utilization_pct = total_ar_balance / credit_limit x 100%`
- Menampilkan risk level:
  - Low, Medium, High, Over Limit.
- Menampilkan daftar outlet dengan utilisasi tertinggi.

### d) Top Outlet (`top-outlets`)
- Ranking outlet berdasarkan total AR terbesar.
- Menampilkan balance, jumlah invoice, max overdue, CM.
- Dipakai sebagai prioritas penagihan.

### e) Payment (`payment`)
- Summary perilaku bayar:
  - belum bayar sama sekali,
  - bayar sebagian,
  - lunas.
- Daftar `worst payers` berdasarkan persentase bayar terendah.

### f) Salesman (`salesman`)
- Ringkasan AR per salesman:
  - total balance,
  - jumlah outlet,
  - jumlah invoice,
  - rata-rata overdue,
  - invoice bandel (`CM >= 3`).

### g) Giro (`giro`)
- Rekap giro per bank.
- Daftar detail giro (nomor giro, outlet, bank, amount, due date).

### h) Detail (`detail`)
- Tabel detail invoice outstanding dengan pencarian teks.
- Search bisa berdasarkan outlet, kode outlet, PFI/SN, atau nama salesman.

## 8. Integrasi AR di Modul Lain

### 8.1 Salesman Profile (`/salesmen/{id}`)
Menampilkan section AR per salesman:
- total piutang,
- total overdue,
- outlet berpiutang,
- outlet bandel (CM>=3),
- rata-rata overdue,
- collection rate,
- daftar outlet piutang + drilldown ke invoice.

### 8.2 My Dashboard (role salesman)
Menampilkan ringkasan AR pribadi:
- outstanding, overdue, outlet, max overdue,
- top outlet piutang + rincian faktur,
- daftar invoice kritis (`overdue_days >= 60`).

### 8.3 Tutup Buku / Period Snapshot
Saat periode ditutup:
- sistem mengambil import AR completed terbaru dalam bulan periode tersebut,
- menyimpan KPI AR ke `closing_snapshots` (data beku/historis), termasuk:
  - total outstanding,
  - total overdue,
  - total AR amount,
  - total paid,
  - jumlah outlet/invoice,
  - average & max overdue,
  - aging distribution,
  - AR per salesman.

### 8.4 TV Dashboard
Leaderboard salesman menampilkan tambahan `Piutang` (AR balance) per salesman dari import AR terbaru.

## 9. Struktur Data AR (Ringkas)

### 9.1 Tabel `ar_import_logs`
Menyimpan metadata batch import:
- file, tanggal laporan, sheet,
- status proses,
- total/sukses/gagal,
- error text,
- waktu mulai/selesai.

### 9.2 Tabel `ar_receivables`
Menyimpan detail invoice piutang:
- outlet, salesman, principal,
- dokumen (`pfi_sn`, `doc_date`, `due_date`, `top`),
- nilai (`ar_amount`, `ar_paid`, `ar_balance`, `credit_limit`),
- collection (`cm`, `overdue_days`),
- info giro,
- `branch_sheet` (asal sheet).

## 10. Mapping Kolom Excel untuk AR

Mapping kolom AR ada di `config/import_columns.php` dengan prefix `ar_*`.
Contoh:
- `ar_pfi_sn` -> `pfisn`
- `ar_outlet_id` -> `outlet_id`
- `ar_ar_balance` -> `ar_balance`
- `ar_overdue_days` -> `over_due`

Catatan penting:
- halaman Settings > Column Mapping saat ini fokus untuk mapping import Sales,
- mapping AR dikelola dari file config.

## 11. Definisi Istilah (Untuk Narasi Dokumentasi)

- `AR Amount`: nilai total tagihan invoice.
- `AR Paid`: nilai yang sudah dibayar.
- `AR Balance`: sisa piutang belum terbayar.
- `Overdue Days`: jumlah hari keterlambatan dari due date.
- `CM (Collection Mention)`: berapa kali surat tagihan/penagihan dicetak atau dilakukan.
- `Credit Utilization`: persentase pemakaian limit kredit (`AR Balance / Credit Limit`).
- `Outlet Bandel`: invoice/outlet dengan `CM >= 3` namun masih punya balance.

## 12. Catatan Operasional

- Import AR berjalan via job queue (`ProcessArImport`).
- Jika environment memakai queue async, worker harus aktif agar status import bergerak dari `pending` ke `completed`/`failed`.
- Dashboard AR selalu berbasis data import terbaru yang completed, jadi urutan tanggal laporan sangat menentukan data yang tampil.

---

Dokumentasi ini disusun dari implementasi aktual di controller, model, migration, job import, dan view AR pada project DistoraVision.
