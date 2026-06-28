<?php

/**
 * KOD ÖRNEĞİ — Eşzamanlı siparişlerde yarış-durumu (race condition) çözümü.
 *
 * Problem: Self-servis QR'dan aynı anda iki müşteri sipariş verdiğinde, ikisi de
 * "son numara + 1" hesabını aynı anda okuyup aynı sıra numarasını (örn. #5) alabilir.
 *
 * Çözüm: Sıra numarasını DB::transaction içinde lockForUpdate() ile üretmek.
 * lockForUpdate() ilgili satırlara pesimistik kilit koyar; ikinci istek, birincinin
 * transaction'ı bitene kadar bekler → çakışan numara imkânsız hâle gelir.
 *
 * (MenuController@store — self-servis sipariş oluşturma akışından alınmıştır.)
 */

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Sipariş, masanın KENDİ tenant'ının adminine bağlanır (çapraz-tenant sızıntısını önler).
$adminId = User::where('role', 'admin')->where('tenant_id', $table->tenant_id)->value('id');

$order = DB::transaction(function () use ($request, $table, $adminId) {
    // Sıra numarasını transaction içinde, satır kilidiyle üret →
    // eşzamanlı siparişlerde aynı numara çakışması olmaz.
    $orderNumber = (Order::withoutGlobalScope('tenant')
        ->where('tenant_id', $table->tenant_id)
        ->whereDate('created_at', today())
        ->where('order_type', 'takeaway')
        ->lockForUpdate()
        ->max('order_number') ?? 0) + 1;

    $order = Order::create([
        'tenant_id'      => $table->tenant_id,
        'table_id'       => null,
        'user_id'        => $adminId,
        'status'         => 'open',
        'order_type'     => 'takeaway',
        'order_number'   => $orderNumber,
        'customer_name'  => $request->customer_name,
        'display_status' => 'preparing',
    ]);

    // ... sipariş kalemleri aynı transaction içinde eklenir ...

    return $order;
});
