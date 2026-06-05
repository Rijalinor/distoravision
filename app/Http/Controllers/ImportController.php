<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSecondaryDataImport;
use App\Models\AccountingPeriod;
use App\Models\ImportLog;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ImportController extends Controller
{
    public function index()
    {
        $imports = ImportLog::with('user')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('imports.index', compact('imports'));
    }

    public function create()
    {
        return view('imports.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:51200',
            'period' => 'required|date_format:Y-m',
            'import_mode' => 'required|in:tambah,ganti',
        ]);

        // ── Period Guard: block import to closed periods ──
        if (AccountingPeriod::isPeriodClosed($request->period)) {
            return back()->with('error', 'Tidak dapat mengimport data. Periode '.$request->period.' sudah ditutup (Tutup Buku). Hubungi Admin untuk membuka kembali.');
        }

        $file = $request->file('file');

        // Note: use 'local' disk which saves to storage/app by default
        // or just explicit store in imports directory
        $filePath = $file->store('imports', 'local');

        // Create import log
        $importLog = ImportLog::create([
            'user_id' => auth()->id(),
            'filename' => $file->getClientOriginalName(),
            'period' => $request->period,
            'status' => 'pending',
            'started_at' => now(),
        ]);

        // Dispatch background job
        ProcessSecondaryDataImport::dispatch($importLog, $filePath, $request->import_mode);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($importLog)
            ->withProperties(['file' => $file->getClientOriginalName(), 'period' => $request->period, 'mode' => $request->import_mode])
            ->log('mengunggah data sales secondary (mode: '.$request->import_mode.')');

        return redirect()->route('imports.index')
            ->with('success', 'File berhasil diupload dan sedang diproses di background. Refresh halaman ini nanti untuk melihat status.');
    }

    public function show(ImportLog $import)
    {
        return view('imports.show', compact('import'));
    }

    public function destroy(ImportLog $import)
    {
        // Backward-compatible delete:
        // - If import_log_id column exists, delete by exact import log (safe).
        // - If not, fallback to period delete to avoid runtime failure.
        if (Schema::hasColumn('transactions', 'import_log_id')) {
            Transaction::where('import_log_id', $import->id)->delete();
        } else {
            Transaction::where('period', $import->period)->delete();
        }

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['period' => $import->period])
            ->log('menghapus data log import sales dan transaksi terkait');

        $import->delete();

        return redirect()->route('imports.index')->with('success', 'Import log dan data terkait berhasil dihapus.');
    }
}
