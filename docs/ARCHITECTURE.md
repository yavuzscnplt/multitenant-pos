# Mimari

## Genel bakış

Laravel 12 üzerinde, **paylaşımlı veritabanı + satır bazlı kiracı izolasyonu**
(shared-database, row-level multi-tenancy) modeli kullanan bir POS platformu.

```
Süper Admin
   └── Franchise (marka)
          └── Tenant (şube)
                 ├── Admin (şube yöneticisi)
                 └── Garson
```

Her iş verisi tablosu bir `tenant_id` taşır. Aktif kiracı istek başına
`TenantContext` (request-scoped statik) içinde tutulur; `TenantMiddleware`
oturum açan kullanıcının kiracısını oraya yazar; `HasTenant` trait'i de her
Eloquent sorgusuna global scope olarak uygular.

## Tenant izolasyonu nasıl çalışır

1. **`TenantMiddleware`** — kimliği doğrulanmış kullanıcının `tenant`'ını
   `TenantContext::set()` ile aktif eder.
2. **`HasTenant` trait** — modele iki davranış ekler:
   - `addGlobalScope('tenant', ...)` → her `SELECT` otomatik olarak
     `WHERE tenant_id = <aktif>` ile filtrelenir.
   - `creating` event → yeni kayda `tenant_id` otomatik yazılır.
3. **Bilinçli çapraz-kiracı erişim** — süper admin konsolidasyonu gibi yerlerde
   `withoutGlobalScope('tenant')` veya `scopeForTenant()` ile **açıkça** izolasyon
   aşılır; yani varsayılan güvenli, istisna görünür.

Bu yaklaşımın faydası: geliştirici her sorguda `tenant_id` filtresini elle
yazmayı unutsa bile veri sızmaz — güvenlik **varsayılan**, opt-out değil opt-in.

## Sipariş akışları

Üç giriş kanalı, tek veri modeli (`Order` + `OrderItem`):

| Kanal | order_type | table_id | customer_name |
|-------|-----------|----------|---------------|
| POS (masa) | dine_in | dolu | null |
| Self-servis QR | takeaway | null | dolu |
| Paket / al-götür | takeaway | null | dolu |

Stok düşümü kanaldan bağımsızdır çünkü `OrderItem` model event'lerine bağlıdır
(bkz. kod örneği). Böylece yeni bir sipariş kanalı eklendiğinde stok mantığına
dokunmaya gerek kalmaz.

## Eşzamanlılık

Self-servis sipariş numarası gün+şube bazında artar. Aynı anda gelen siparişlerde
çakışmayı önlemek için numara üretimi `DB::transaction` içinde `lockForUpdate()`
ile yapılır (pesimistik kilit).

## Para mantığı

Tüm para hesapları (`subtotal`, `remaining`, `overpaid`, ikram, hesap bölme) `Order`
modelinde computed attribute olarak toplanır — controller'lara dağılmaz. Bu mantık
`tests/Unit/OrderMoneyTest.php` ile regresyona karşı korunur.

## Gerçek-zamanlılık

Kasaya yeni sipariş bildirimi ve mutfak ekranı şu an **polling** tabanlıdır (kısa
aralıklı JSON istekleri). WebSocket'e geçiş yol haritasındadır; mevcut ölçek için
polling yeterli ve operasyonel olarak daha basittir.

## Paketleme

- Geliştirme: `docker-compose.dev.yml` (otomatik migrate + sahte demo seed).
- Üretim: ayrı `docker-compose.yml` + sertleştirilmiş PHP yapılandırması, harici
  reverse-proxy varsayımı.
