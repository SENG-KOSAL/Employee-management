<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;

class ActiveCompany
{
    protected ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function clear(): void
    {
        $this->company = null;
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function require(): Company
    {
        if ($this->company === null) {
            throw new InvalidArgumentException('Active company is not set in context.');
        }

        return $this->company;
    }
}
