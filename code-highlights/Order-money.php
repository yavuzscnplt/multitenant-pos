<?php

/**
 * KOD ÖRNEĞİ — Merkezi para mantığı (computed attributes).
 *
 * Ara toplam, kalan, fazla ödeme; ikram (is_gift) ve hesap bölme (is_split_paid)
 * gibi tüm para kuralları controller'lara dağılmaz — Order modelinde tek yerde,
 * okunur computed attribute'lar olarak toplanır. Bu mantık tests/Unit/OrderMoneyTest
 * ile regresyona karşı korunur.
 */

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasTenant;

    // Ödenmesi gereken ara toplam — ikram ve ayrı-ödenmiş kalemler hariç,
    // kalem bazlı iskonto ve ekstralar dahil.
    public function getSubtotalAttribute(): float
    {
        return $this->items->sum(function ($item) {
            if ($item->is_gift || $item->is_split_paid) return 0;
            $extrasTotal = $item->extras->sum('price');
            $linePrice   = ($item->unit_price + $extrasTotal) * $item->quantity;
            return $linePrice * (1 - $item->discount / 100);
        });
    }

    public function getPaidAmountAttribute(): float
    {
        return $this->payments->sum('amount');
    }

    // Kalan = ara toplam - (ödenen - ayrı ödenmiş kalemler). Asla negatif olmaz.
    public function getRemainingAttribute(): float
    {
        $effectivePaid = $this->paid_amount - $this->split_paid_total;
        return max(0, $this->subtotal - $effectivePaid);
    }

    // Fazla ödeme (para üstü hesabı) — bahşiş hariç tutulur.
    public function getOverpaidAttribute(): float
    {
        $total = $this->closed_subtotal ?? $this->all_items_total;
        return max(0, $this->paid_amount - $total - (float) $this->tip_amount);
    }
}
