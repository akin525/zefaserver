<?php

namespace App\Traits;

use App\Helpers\AuditHelper;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            AuditHelper::logCreated($model);
        });

        static::updated(function ($model) {
            AuditHelper::logUpdated($model, $model->getOriginal());
        });

        static::deleted(function ($model) {
            AuditHelper::logDeleted($model);
        });
    }

    /**
     * Get audit name for this model
     */
    public function getAuditName(): string
    {
        return $this->name ?? $this->title ?? $this->email ?? $this->id ?? 'Unknown';
    }

    /**
     * Get audits for this model
     */
    public function audits()
    {
        return $this->morphMany(\App\Models\AdminAudit::class, 'auditable');
    }
}
