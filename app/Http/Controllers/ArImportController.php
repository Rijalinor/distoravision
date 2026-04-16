<?php

namespace App\Http\Controllers;

use App\Models\ArImportLog;
use Illuminate\Http\Request;

class ArImportController extends Controller
{
    public function index()
    {
        $imports = ArImportLog::with('user')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('ar.import-index', compact('imports'));
    }

    public function create()
    {
        return view('ar.import-create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:51200',
            'report_date' => 'required|date',
            'sheet_name' => 'required|string|max:50',
        ]);

        $file = $request->file('file');
        $filePath = $file->store('imports', 'local');

        $importLog = ArImportLog::create([
            'user_id' => auth()->id(),
            'filename' => $file->getClientOriginalName(),
            'report_date' => $request->report_date,
            'sheet_name' => $request->sheet_name,
            'status' => 'pending',
            'started_at' => now(),
        ]);

        \App\Jobs\ProcessArImport::dispatch($importLog, $filePath, $request->sheet_name);

        return redirect()->route('ar.imports.index')
            ->with('success', 'File AR berhasil diupload dan sedang diproses. Refresh halaman ini untuk melihat status.');
    }

    public function destroy(ArImportLog $arImportLog)
    {
        $arImportLog->receivables()->delete();
        $arImportLog->delete();

        return redirect()->route('ar.imports.index')
            ->with('success', 'Import AR dan data terkait berhasil dihapus.');
    }
}
