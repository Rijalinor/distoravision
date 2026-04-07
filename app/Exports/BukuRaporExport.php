<?php

namespace App\Exports;

use App\Exports\Sheets\SummarySheet;
use App\Exports\Sheets\SalesmanSheet;
use App\Exports\Sheets\ProductSheet;
use App\Exports\Sheets\OutletSheet;
use App\Exports\Sheets\ParetoSheet;
use App\Exports\Sheets\RfmSheet;
use App\Exports\Sheets\ChurnSheet;
use App\Exports\Sheets\DiscountSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Http\Request;

class BukuRaporExport implements WithMultipleSheets
{
    protected Request $request;
    protected string $period;
    protected string $principalName;

    public function __construct(Request $request, string $period, string $principalName)
    {
        $this->request       = $request;
        $this->period        = $period;
        $this->principalName = $principalName;
    }

    public function sheets(): array
    {
        return [
            new SummarySheet($this->request, $this->period, $this->principalName),
            new SalesmanSheet($this->request, $this->period),
            new ProductSheet($this->request, $this->period),
            new OutletSheet($this->request, $this->period),
            new ParetoSheet($this->request, $this->period),
            new RfmSheet($this->request, $this->period),
            new ChurnSheet($this->request, $this->period),
            new DiscountSheet($this->request, $this->period),
        ];
    }
}
