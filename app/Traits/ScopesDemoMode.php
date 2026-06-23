<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopesDemoMode
{
    public static function bootScopesDemoMode(): void
    {
        static::addGlobalScope('demo_mode', function (Builder $builder) {
            if (auth()->check()) {
                $user = auth()->user();
                $isDemoUser = ($user->email === 'demo@admin.com' || $user->role === 'demo');

                $table = $builder->getModel()->getTable();

                if (in_array($table, ['import_logs', 'ar_import_logs', 'sales_per_import_logs'])) {
                    if ($isDemoUser) {
                        $builder->where($table.'.user_id', $user->id);
                    } else {
                        $demoUserIds = User::where('role', 'demo')
                            ->orWhere('email', 'demo@admin.com')
                            ->pluck('id');

                        if ($demoUserIds->isNotEmpty()) {
                            $builder->whereNotIn($table.'.user_id', $demoUserIds);
                        }
                    }
                } elseif ($table === 'transactions') {
                    if ($isDemoUser) {
                        $builder->whereHas('importLog', function ($q) {
                            $q->where('user_id', auth()->id());
                        });
                    } else {
                        $builder->where(function ($query) {
                            $query->whereNull('import_log_id')
                                ->orWhereHas('importLog', function ($q) {
                                    $demoUserIds = User::where('role', 'demo')
                                        ->orWhere('email', 'demo@admin.com')
                                        ->pluck('id');

                                    if ($demoUserIds->isNotEmpty()) {
                                        $q->whereNotIn('user_id', $demoUserIds);
                                    }
                                });
                        });
                    }
                } elseif ($table === 'ar_receivables') {
                    if ($isDemoUser) {
                        $builder->whereHas('importLog', function ($q) {
                            $q->where('user_id', auth()->id());
                        });
                    } else {
                        $builder->where(function ($query) {
                            $query->whereNull('ar_import_log_id')
                                ->orWhereHas('importLog', function ($q) {
                                    $demoUserIds = User::where('role', 'demo')
                                        ->orWhere('email', 'demo@admin.com')
                                        ->pluck('id');

                                    if ($demoUserIds->isNotEmpty()) {
                                        $q->whereNotIn('user_id', $demoUserIds);
                                    }
                                });
                        });
                    }
                } elseif (in_array($table, ['sales_per_transactions', 'sales_per_stocks'])) {
                    $fk = 'sales_per_import_log_id';
                    if ($isDemoUser) {
                        $builder->whereHas('importLog', function ($q) {
                            $q->where('user_id', auth()->id());
                        });
                    } else {
                        $builder->where(function ($query) use ($fk) {
                            $query->whereNull($fk)
                                ->orWhereHas('importLog', function ($q) {
                                    $demoUserIds = User::where('role', 'demo')
                                        ->orWhere('email', 'demo@admin.com')
                                        ->pluck('id');

                                    if ($demoUserIds->isNotEmpty()) {
                                        $q->whereNotIn('user_id', $demoUserIds);
                                    }
                                });
                        });
                    }
                }
            }
        });
    }
}
