<?php

/**
 * KOD ÖRNEĞİ — Çok kiracılı (multi-tenant) veri izolasyonu.
 *
 * Bu trait'i kullanan her model, otomatik olarak yalnızca aktif kiracının (tenant)
 * verisini görür ve yeni kayıtlara tenant_id'yi kendisi yazar.
 *
 * Neden önemli: Güvenlik VARSAYILAN hâle gelir. Geliştirici bir controller'da
 * `where('tenant_id', ...)` yazmayı unutsa bile başka bir şubenin verisi sızmaz.
 * İzolasyonu aşmak (örn. süper admin konsolidasyonu) ancak `withoutGlobalScope`
 * ile BİLİNÇLİ olarak yapılır → güvenli varsayılan, görünür istisna.
 */

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait HasTenant
{
    protected static function bootHasTenant(): void
    {
        // Her SELECT otomatik olarak aktif tenant'a filtrelenir.
        static::addGlobalScope('tenant', function (Builder $query) {
            if ($tenantId = TenantContext::id()) {
                $query->where((new static)->getTable() . '.tenant_id', $tenantId);
            }
        });

        // Yeni kayıtlara tenant_id otomatik atanır.
        static::creating(function ($model) {
            if (empty($model->tenant_id) && $tenantId = TenantContext::id()) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /** İzolasyonu bilinçli olarak aşıp belirli bir kiracıyı sorgulamak için. */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScope('tenant')
            ->where($this->getTable() . '.tenant_id', $tenantId);
    }
}
