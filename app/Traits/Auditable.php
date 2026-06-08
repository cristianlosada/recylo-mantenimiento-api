<?php

namespace App\Traits;

use App\Observers\AuditObserver;

trait Auditable
{
    /**
     * Boot the trait
     */
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    /**
     * Campos que no deben ser auditados
     */
    protected function getAuditExclude(): array
    {
        return $this->auditExclude ?? [];
    }
}
