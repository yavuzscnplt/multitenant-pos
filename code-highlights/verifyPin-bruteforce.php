<?php

/**
 * KOD ÖRNEĞİ — Yönetici PIN doğrulama: hash + brute-force koruması.
 *
 * POS içinde yetki yükseltmek için yönetici PIN'i girilir. İki güvenlik kararı:
 *   1) PIN'ler düz metin DEĞİL, hash'li saklanır → yalnızca Hash::check ile karşılaştırılır.
 *   2) RateLimiter ile brute-force engellenir; üstelik YALNIZCA başarısız denemeler
 *      sayılır (doğru PIN sayacı sıfırlar) → meşru kullanıcı cezalandırılmaz.
 *
 * (AuthController@verifyPin'den alınmıştır.)
 */

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

public function verifyPin(\Illuminate\Http\Request $request)
{
    $request->validate(['pin' => 'required|string']);

    $user = Auth::user();

    // Brute-force koruması: 60 sn pencerede 10 başarısız deneme limiti.
    $key = 'pin-verify:' . $user->id;
    if (RateLimiter::tooManyAttempts($key, 10)) {
        $secs = RateLimiter::availableIn($key);
        return response()->json([
            'success' => false,
            'message' => "Çok fazla hatalı deneme. {$secs} sn sonra tekrar deneyin.",
        ], 429);
    }

    $admins = User::where('role', 'admin')
        ->where('tenant_id', $user->tenant_id)
        ->where('is_active', true)
        ->whereNotNull('pin')
        ->get();

    foreach ($admins as $admin) {
        // PIN'ler her zaman hash'li — düz-metin karşılaştırma YOK.
        if ($admin->pin && Hash::check($request->pin, $admin->pin)) {
            RateLimiter::clear($key); // doğru PIN → sayacı sıfırla
            session(['admin_override' => true, 'admin_override_id' => $admin->id]);
            return response()->json(['success' => true, 'name' => $admin->name]);
        }
    }

    RateLimiter::hit($key, 60); // başarısız → sayacı artır (60 sn pencere)
    return response()->json(['success' => false, 'message' => 'Hatalı PIN!'], 401);
}
