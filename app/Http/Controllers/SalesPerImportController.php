<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesPerImportRequest;
use App\Jobs\ProcessSalesPerImport;
use App\Models\SalesPerImportLog;
use App\Models\SalesPerStock;
use App\Models\SalesPerTransaction;
use Illuminate\Support\Facades\DB;

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

    public function store(StoreSalesPerImportRequest $request)
    {
        $validated = $request->validated();

        $file = $request->file('file');
        $filePath = $file->store('imports', 'local');

        $importLog = SalesPerImportLog::create([
            'user_id' => auth()->id(),
            'filename' => $file->getClientOriginalName(),
            'period' => $validated['period'],
            'status' => 'pending',
            'started_at' => now(),
        ]);

        ProcessSalesPerImport::dispatch($importLog, $filePath, $validated['import_mode']);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($importLog)
            ->withProperties(['file' => $file->getClientOriginalName(), 'period' => $validated['period'], 'mode' => $validated['import_mode']])
            ->log('mengunggah data Sales Per (mode: '.$validated['import_mode'].')');

        return redirect()->route('sales-per.imports.index')
            ->with('success', 'File berhasil diupload dan sedang diproses. Refresh halaman untuk melihat status.');
    }

    public function destroy(SalesPerImportLog $salesPerImportLog)
    {
        DB::transaction(function () use ($salesPerImportLog) {
            // Use withoutGlobalScope to ensure ALL related data is deleted,
            // regardless of the current user's ACL scope.
            SalesPerTransaction::withoutGlobalScope('acl')
                ->where('sales_per_import_log_id', $salesPerImportLog->id)
                ->delete();

            SalesPerStock::withoutGlobalScope('acl')
                ->where('sales_per_import_log_id', $salesPerImportLog->id)
                ->delete();

            activity()
                ->causedBy(auth()->user())
                ->withProperties(['period' => $salesPerImportLog->period])
                ->log('menghapus data Sales Per import');

            $salesPerImportLog->delete();
        });

        cache()->flush();

        return redirect()->route('sales-per.imports.index')->with('success', 'Import log dan data terkait berhasil dihapus.');
    }
}
