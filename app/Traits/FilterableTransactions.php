<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FilterableTransactions
{
    /**
     * Scope a query to apply global filters (start_period, end_period, principal_id).
     *
     * @return Builder
     */
    public function scopeWithFilters(Builder $query, Request $request)
    {
        // Determine if ANY period filter is present
        $hasPeriod = $request->has('period') && ! empty($request->get('period'));
        $hasStartPeriod = $request->has('start_period') && ! empty($request->get('start_period'));
        $hasEndPeriod = $request->has('end_period') && ! empty($request->get('end_period'));

        if ($hasPeriod && ! $hasStartPeriod && ! $hasEndPeriod) {
            // Legacy single-period mode
            $query->where($this->getTable().'.period', $request->get('period'));

        } elseif ($hasStartPeriod || $hasEndPeriod) {
            // Range mode
            if ($hasStartPeriod) {
                $query->where($this->getTable().'.period', '>=', $request->get('start_period'));
            }
            if ($hasEndPeriod) {
                $query->where($this->getTable().'.period', '<=', $request->get('end_period'));
            }

        } else {
            // ── NO filter at all → default to latest available period ──────────
            // This prevents the "scan all periods on first load" performance issue.
            $latestPeriod = self::max('period');

            if ($latestPeriod) {
                $query->where($this->getTable().'.period', $latestPeriod);
            }
        }

        // Principal Filter
        if ($request->has('principal_id') && ! empty($request->get('principal_id')) && $request->get('principal_id') !== 'all') {
            $principalId = $request->get('principal_id');

            $isProductsJoined = collect($query->getQuery()->joins)->pluck('table')->contains('products');

            if (! $isProductsJoined) {
                $query->whereHas('product', function ($q) use ($principalId) {
                    $q->where('principal_id', $principalId);
                });
            } else {
                $query->where('products.principal_id', $principalId);
            }
        }

        return $query;
    }
}
