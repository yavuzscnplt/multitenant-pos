<?php

/**
 * KOD ÖRNEĞİ — Tek noktada stok düşümü (model event'leriyle).
 *
 * Problem: Sipariş kalemi 3 farklı kanaldan oluşabiliyor (POS, self-servis QR, paket).
 * Stok düşümünü her kanala ayrı yazmak → kod tekrarı + tutarsızlık riski.
 *
 * Çözüm: Stok mantığını OrderItem'ın model event'lerine bağlamak. Kalem hangi
 * kanaldan oluşturulursa oluşturulsun, created/deleted/updated event'leri tetiklenir
 * ve stok tek yerden, tutarlı şekilde güncellenir. Yeni bir sipariş kanalı eklemek
 * stok koduna dokunmayı gerektirmez.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    /** Stok düşümü tüm sipariş yollarından (POS, self-servis, paket) tek yerde. */
    protected static function booted(): void
    {
        static::created(fn ($item) => $item->adjustProductStock(-(int) $item->quantity));
        static::deleted(fn ($item) => $item->adjustProductStock(+(int) $item->quantity));
        static::updated(function ($item) {
            if ($item->wasChanged('quantity')) {
                // Miktar arttıysa stok düşer, azaldıysa iade edilir.
                $item->adjustProductStock(
                    (int) $item->getOriginal('quantity') - (int) $item->quantity
                );
            }
        });
    }

    public function adjustProductStock(int $delta): void
    {
        if (!$this->product_id || $delta === 0) return;

        // Stok, ürünü oluşturan kiracıya ait; global scope dışında erişilir.
        $product = Product::withoutGlobalScope('tenant')->find($this->product_id);

        if (!$product || $product->stock === null) return; // null = stok takibi kapalı

        // saveQuietly() → event döngüsünü tetiklemeden, negatife düşmeden güncelle.
        $product->forceFill(['stock' => max(0, (int) $product->stock + $delta)])
            ->saveQuietly();
    }
}
