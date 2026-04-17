# Fix: Data Duplikat & pH = 0 di Production

## Masalah
- ❌ Data duplikat di log
- ❌ pH menampilkan 0 (seharusnya ada nilai)
- ❌ Terjadi race condition saat multiple subscribers berjalan

## Penyebab
Ada **lebih dari 1 MQTT subscriber** running bersamaan:
- `php artisan mqtt:subscribe` (via PM2?)
- `node ingest-mqtt.js` atau `node node-subscriber.js`?
- `php artisan mqtt:listen`?

Semua menerima pesan MQTT yang sama → conflict write → data rusak

## Solusi

### 1. Hentikan Semua Subscriber
```bash
# Check proses yang aktif
pm2 list
ps aux | grep mqtt
ps aux | grep node
ps aux | grep artisan

# Stop PM2 services
pm2 stop all

# Kill any remaining MQTT processes
pkill -f mqtt:subscribe
pkill -f ingest-mqtt
pkill -f node-subscriber
pkill -f mqtt:listen
```

### 2. Jalankan Hanya 1 Subscriber (Rekomendasi)
```bash
# Start HANYA mqtt:subscribe via PM2
pm2 start "php artisan mqtt:subscribe" \
  --name hidroponik-mqtt \
  --cwd /var/www/hidroponik \
  --output /var/www/hidroponik/storage/logs/mqtt.log \
  --error /var/www/hidroponik/storage/logs/mqtt-error.log

# Verify hanya 1 yang berjalan
pm2 list

# Save config
pm2 save
pm2 startup
```

### 3. Clean Up Database (Optional - Jika perlu reset duplikat)
```bash
# Backup dulu!
mysqldump -u your_user -p hidroponik_db > backup_$(date +%Y%m%d_%H%M%S).sql

# Delete duplikat records (keep earliest)
DELETE FROM telemetries t1 
WHERE t1.id NOT IN (
  SELECT id FROM (
    SELECT MIN(id) as id 
    FROM telemetries 
    GROUP BY kebun, DATE_FORMAT(recorded_at, '%Y-%m-%d %H:%i')
  ) t2
);
```

### 4. Monitor di Production
```bash
# Check logs real-time
pm2 logs hidroponik-mqtt

# Verify data quality
mysql -u your_user -p hidroponik_db -e \
  "SELECT kebun, recorded_at, ph, tds FROM telemetries ORDER BY recorded_at DESC LIMIT 10;"
```

### 5. Verifikasi di Frontend
- Buka: `https://domain.com/log`
- Cek apakah pH sudah ada value (bukan 0)
- Cek apakah timestamp unik (tidak duplikat)

---

## Checklist
- [ ] Pastikan hanya 1 subscriber running
- [ ] Check PM2 logs untuk error
- [ ] Verify database tidak ada duplikat
- [ ] Test realtime data masuk dengan benar
- [ ] pH menampilkan nilai (bukan 0)

## Prevention
Tambahkan ke `.env`:
```env
# Only allow ONE mqtt subscriber
# Jangan jalankan service lain yang subscribe ke MQTT
```
