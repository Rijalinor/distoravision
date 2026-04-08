<?php

namespace App\Http\Controllers;

use App\Imports\SecondaryDataImport;
use App\Models\ImportLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

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
        ]);

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
        \App\Jobs\ProcessSecondaryDataImport::dispatch($importLog, $filePath);

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
            \App\Models\Transaction::where('import_log_id', $import->id)->delete();
        } else {
            \App\Models\Transaction::where('period', $import->period)->delete();
        }

        $import->delete();

        return redirect()->route('imports.index')->with('success', 'Import log dan data terkait berhasil dihapus.');
    }
}
