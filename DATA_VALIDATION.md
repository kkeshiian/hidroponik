# Data Validation untuk Telemetry MQTT

## Fix untuk Masalah Data Invalid

Sudah diupdate di `MqttSubscribe.php` untuk reject data yang tidak valid sebelum masuk database.

### Validasi yang Diterapkan

#### 1. **Timestamp di Masa Depan**
- ❌ **Ditolak**: Data dengan timestamp > 5 menit di masa depan
- ✅ **Diterima**: Data dengan timestamp terukur atau saat ini ± 5 menit

**Contoh:**
- Jam server: 19:01
- Data masuk: 19:58 → ❌ **DITOLAK** (57 menit ke masa depan)
- Data masuk: 19:05 → ✅ Diterima (4 menit ke masa depan)

#### 2. **Sensor Value Invalid (pH=0 & TDS=0)**
- ❌ **Ditolak**: Data dengan KEDUA nilai pH=0 AND TDS=0 
- ✅ **Diterima**: Data dengan salah satu atau kedua ada nilai

**Contoh:**
| pH | TDS | Validasi | Alasan |
|----|-----|----------|--------|
| 0  | 0   | ❌ REJECT | Keduanya 0 = sensor error |
| 0  | 851 | ✅ OK    | TDS ada, pH mungkin gagal kalibrasi |
| 2.66| 926 | ✅ OK    | Keduanya valid |

### Testing Validasi

#### Check Rejected Data di Logs
```bash
# Lihat data yang ditolak
tail -f /var/www/hidroponik/storage/logs/laravel.log | grep "rejected"

# Contoh output:
# [2026-04-17 11:01:21] laravel.WARNING: Telemetry rejected: timestamp in future 
# [2026-04-17 11:01:22] laravel.WARNING: Telemetry rejected: invalid sensor values
```

#### Manual Testing
```php
# Masuk ke Laravel Tinker
php artisan tinker

# Lihat rejected warnings di log
file_get_contents(storage_path('logs/laravel.log'));
```

### Konfigurasi Threshold

Edit di `MqttSubscribe.php` line 835 jika ingin ubah tolerance:
```php
$futureThresholdMinutes = 5; // Ubah nilai sesuai kebutuhan
```

### Cleanup Database

Jika perlu hapus data invalid yang sudah masuk sebelumnya:

```sql
-- Backup dulu!
-- Hapus data dengan pH=0 dan TDS=0
DELETE FROM telemetries 
WHERE ph = 0 AND tds = 0 AND recorded_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Atau lihat dulu tanpa hapus
SELECT * FROM telemetries 
WHERE ph = 0 AND tds = 0 
ORDER BY recorded_at DESC 
LIMIT 10;
```

### Restart Service

```bash
pm2 restart hidroponik-mqtt
pm2 logs hidroponik-mqtt
```

## Monitoring

Setiap rejection di-log dengan:
- ✅ Timestamp
- ✅ Nama kebun (device)
- ✅ Nilai yang ditolak
- ✅ Alasan rejection

Cek dashboard Log Data Anda → data invalid tidak akan lagi muncul! 🎯
