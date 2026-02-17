<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\ActiveCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function (Model $model) {
            if ($model->getAttribute('company_id')) {
                return;
            }

            $activeCompany = app(ActiveCompany::class);
            if ($activeCompany->id()) {
                $model->setAttribute('company_id', $activeCompany->id());
            }
        });

        static::addGlobalScope('company', function (Builder $builder) {
            if (app()->runningInConsole()) {
                return;
            }

            $activeCompany = app(ActiveCompany::class);
            $companyId = $activeCompany->id();

            if ($companyId === null) {
                // No active company means no tenant data should be returned.
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->getModel()->getTable() . '.company_id', $companyId);
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $builder, int $companyId): Builder
    {
        return $builder->where($builder->getModel()->getTable() . '.company_id', $companyId);
    }
}
