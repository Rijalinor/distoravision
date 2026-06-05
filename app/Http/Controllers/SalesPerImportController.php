<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSalesPerImport;
use App\Models\SalesPerImportLog;
use App\Models\SalesPerStock;
use App\Models\SalesPerTransaction;
use Illuminate\Http\Request;

class SalesPerImportController extends Controller
{
    public function index()
    {
        $imports = SalesPerImportLog::with('user')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('sales-per.imports.index', compact('imports'));
    }

    public function create()
    {
        return view('sales-per.imports.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:51200',
            'period' => 'required|date_format:Y-m',
            'import_mode' => 'required|in:tambah,ganti',
        ]);

        $file = $request->file('file');
        $filePath = $file->store('imports', 'local');

        $importLog = SalesPerImportLog::create([
            'user_id' => auth()->id(),
            'filename' => $file->getClientOriginalName(),
            'period' => $request->period,
            'status' => 'pending',
            'started_at' => now(),
        ]);

        ProcessSalesPerImport::dispatch($importLog, $filePath, $request->import_mode);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($importLog)
            ->withProperties(['file' => $file->getClientOriginalName(), 'period' => $request->period, 'mode' => $request->import_mode])
            ->log('mengunggah data Sales Per (mode: '.$request->import_mode.')');

        return redirect()->route('sales-per.imports.index')
            ->with('success', 'File berhasil diupload dan sedang diproses. Refresh halaman untuk melihat status.');
    }

    public function destroy(SalesPerImportLog $salesPerImportLog)
    {
        SalesPerTransaction::where('sales_per_import_log_id', $salesPerImportLog->id)->delete();
        SalesPerStock::where('sales_per_import_log_id', $salesPerImportLog->id)->delete();

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['period' => $salesPerImportLog->period])
            ->log('menghapus data Sales Per import');

        $salesPerImportLog->delete();

        return redirect()->route('sales-per.imports.index')->with('success', 'Import log dan data terkait berhasil dihapus.');
    }
}
