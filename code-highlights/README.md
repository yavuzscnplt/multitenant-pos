# Kod örnekleri (code highlights)

Bu klasör, çalışan üründen **seçilmiş** kod parçalarını içerir. Tek başlarına
çalışan bir uygulama oluşturmazlar; amaç çözülen problemleri ve kod kalitesini
göstermektir.

| Dosya | Ne gösteriyor |
|-------|---------------|
| [`HasTenant.php`](HasTenant.php) | Global scope ile otomatik çok-kiracılı veri izolasyonu |
| [`order-number-race-safe.php`](order-number-race-safe.php) | Transaction + `lockForUpdate` ile yarış-durumu çözümü |
| [`OrderItem-stock-events.php`](OrderItem-stock-events.php) | Model event'leriyle tek-noktada stok düşümü |
| [`verifyPin-bruteforce.php`](verifyPin-bruteforce.php) | Hash'li PIN + `RateLimiter` brute-force koruması |
| [`Order-money.php`](Order-money.php) | Computed attribute'larla merkezi para mantığı |
