<?php

/**
 * ========================================================================
 * IMPORT COLUMN MAPPING
 * ========================================================================
 *
 * File ini mendefinisikan mapping antara KEY SISTEM (kiri) dan
 * NAMA KOLOM di Excel (kanan).
 *
 * Jika format Excel berubah, cukup ubah VALUE di sisi kanan.
 * Contoh: jika kolom "outlet_address" di Excel berubah jadi "alamat",
 *         ubah baris:  'outlet_address' => 'alamat',
 *
 * KEY SISTEM (kiri) JANGAN DIUBAH.
 *
 * Mapping ini juga bisa diubah dari halaman Settings > Column Mapping
 * di dalam aplikasi.
 * ========================================================================
 */

return [

    // ── Branch ──────────────────────────────────────────────────────────
    'branch'       => 'branch',
    'branch_name'  => 'branch_name',

    // ── Sales ───────────────────────────────────────────────────────────
    'sales_id'     => 'sales_id',
    'sales_name'   => 'sales_name',

    // ── Principal ───────────────────────────────────────────────────────
    'principle_id'   => 'principle_id',
    'principle_name' => 'principle_name',

    // ── Outlet ──────────────────────────────────────────────────────────
    'outlet_id'      => 'outlet_id',
    'outlet_name'    => 'outlet_name',
    'outlet_address' => 'outlet_address',
    'outlet_city'    => 'outlet_city',
    'outlet_phone'   => 'outlet_phone',
    'route'          => 'route',

    // ── Product ─────────────────────────────────────────────────────────
    'item_no'   => 'item_no',
    'item_name' => 'item_name',
    'uom_sku'   => 'uom_sku',

    // ── Transaction Detail ──────────────────────────────────────────────
    'type'        => 'type',
    'sosn_no'     => 'sosn_no',
    'so_sn_no'    => 'so_sn_no',       // alternatif sosn_no
    'sosn_date'   => 'sosn_date',
    'so_sn_date'  => 'so_sn_date',     // alternatif sosn_date
    'ref_no'      => 'ref_no',
    'pficn_no'    => 'pficn_no',
    'pfi_cn_no_2' => 'pfi_cn_no_2',    // alternatif pficn_no
    'pficn_date'  => 'pficn_date',
    'pfi_cn_date' => 'pfi_cn_date',    // alternatif pficn_date
    'gigr_no'     => 'gigr_no',
    'gi_gr_no'    => 'gi_gr_no',       // alternatif gigr_no
    'gigr_date'   => 'gigr_date',
    'gi_gr_date'  => 'gi_gr_date',     // alternatif gigr_date
    'sicn_no'     => 'sicn_no',
    'si_cn_no'    => 'si_cn_no',       // alternatif sicn_no
    'month'       => 'month',
    'week'        => 'week',
    'warehouse'   => 'warehouse',
    'tax_invoice' => 'tax_invoice',

    // ── Amounts ─────────────────────────────────────────────────────────
    'qty_base'   => 'qty_base',
    'price_base' => 'price_base',
    'gross'      => 'gross',
    'disc_total' => 'disc_total',
    'taxed_amt'  => 'taxed_amt',
    'vat'        => 'vat',
    'ar_amt'     => 'ar_amt',
    'cogs'       => 'cogs',

    // ── AR (Piutang) ────────────────────────────────────────────────────
    // Mapping kolom file AR Excel. Maatwebsite auto-convert header ke snake_case.
    'ar_pfi_sn'            => 'pfisn',
    'ar_outlet_id'         => 'outlet_id',
    'ar_outlet_name'       => 'outlet_name',
    'ar_outlet_ref'        => 'outlet_ref',
    'ar_supervisor'        => 'supervisor',
    'ar_salesman_id'       => 'salesman_id',
    'ar_salesman_name'     => 'salesman_name',
    'ar_principle'         => 'principle',
    'ar_principle_name'    => 'principle_name',
    'ar_doc_date'          => 'doc_date',
    'ar_due_date'          => 'due_date',
    'ar_inv_exchange_date' => 'inv_exchange_date',
    'ar_top'               => 'top',
    'ar_si_cn'             => 'sicn',
    'ar_cm'                => 'cm',
    'ar_cn_balance'        => 'cn_balance',
    'ar_ar_amount'         => 'ar_amount',
    'ar_ar_paid'           => 'ar_paid',
    'ar_ar_balance'        => 'ar_balance',
    'ar_credit_limit'      => 'credit_limit',
    'ar_paid_date'         => 'paid_date',
    'ar_overdue_days'      => 'over_due',
    'ar_giro_no'           => 'giro_no',
    'ar_bank_code'         => 'bank',
    'ar_bank_name'         => 'bank_description',
    'ar_giro_amount'       => 'giro_amount',
    'ar_giro_due_date'     => 'giro_due_date',

];
