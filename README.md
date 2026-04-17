# Hidroponik Alfa - Deploy dan Operasional via Termius

Panduan ini adalah dokumentasi terbaru untuk menjalankan seluruh aplikasi dan service pada hosting Linux melalui Termius.

## 1. Ringkasan Arsitektur Service

- Web app Laravel (Nginx/Apache + PHP-FPM)
- Frontend asset Vite (hasil build static)
- Database MySQL/MariaDB
- MQTT subscriber utama: `php artisan mqtt:subscribe` (wajib aktif untuk telemetry realtime dan Power Consumption)

Service tambahan yang tersedia, tapi tidak wajib untuk production utama:

- `php artisan mqtt:listen`
- `node ingest-mqtt.js`
- `node node-subscriber.js`

Penting:

- Jalankan satu pipeline subscriber saja untuk production.
- Rekomendasi: hanya `mqtt:subscribe` via PM2.
- Menjalankan beberapa subscriber sekaligus dapat menyebabkan data ganda.

## 2. Kebutuhan Server

Minimal software di server:

- Git
- PHP 8.2+ beserta extension umum Laravel
- Composer 2+
- Node.js 20+ dan npm
- MySQL/MariaDB
- PM2 (global npm package)

## 3. First Setup (Sekali Saat Deploy Awal)

```bash
cd /var/www/hidroponik
git clone <repo-url> .
cp .env.example .env

composer install --no-dev --optimize-autoloader
npm ci

php artisan key:generate
php artisan storage:link
php artisan migrate --force

npm run build
php artisan optimize:clear
php artisan optimize
```

Set permission storage dan cache:

```bash
sudo chown -R www-data:www-data /var/www/hidroponik
sudo find /var/www/hidroponik/storage -type d -exec chmod 775 {} \;
sudo find /var/www/hidroponik/bootstrap/cache -type d -exec chmod 775 {} \;
```

## 4. Konfigurasi Environment

Isi file `.env` sesuai server:

```env
APP_NAME="Hidroponik Alfa"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-kamu
APP_TIMEZONE=Asia/Makassar

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hidroponik_db
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

MQTT_HOST=broker.emqx.io
MQTT_BROKER=broker.emqx.io
MQTT_PORT=1883
MQTT_USERNAME=
MQTT_PASSWORD=
MQTT_TOPIC=hidroganik/+/publish
```

Catatan:

- Gunakan timezone yang sama dengan kebutuhan operasional perangkat.
- `MQTT_HOST` dan `MQTT_BROKER` keduanya diisi untuk kompatibilitas command lama dan baru.

## 5. Menjalankan Web App

Gunakan web server (Nginx/Apache) yang mengarah ke folder `public`.

Untuk validasi cepat dari Termius:

```bash
php artisan about
php artisan route:list
```

## 6. Menjalankan MQTT Service (Wajib)

Project sudah menyediakan script PM2 berikut di `package.json`:

- `prod:mqtt:up`
- `prod:mqtt:restart`
- `prod:mqtt:logs`
- `prod:mqtt:down`
- `prod:mqtt:delete`
- `prod:mqtt:save`

Install PM2 (sekali):

```bash
npm i -g pm2
```

Start service subscriber utama:

```bash
cd /var/www/hidroponik
npm run prod:mqtt:up
```

Enable auto start saat reboot:

```bash
pm2 startup
npm run prod:mqtt:save
```

Perintah harian:

```bash
pm2 status
npm run prod:mqtt:logs
npm run prod:mqtt:restart
npm run prod:mqtt:down
```

## 7. Deploy Update (Setiap Rilis)

```bash
cd /var/www/hidroponik

git pull

composer install --no-dev --optimize-autoloader
php artisan migrate --force

npm ci
npm run build

php artisan optimize:clear
php artisan optimize

npm run prod:mqtt:restart
```

Jika ada perubahan env:

```bash
pm2 restart hidroponik-mqtt --update-env
```

## 8. Database dan Integritas Data Telemetry

Versi terbaru menambahkan proteksi agar data tidak dobel per perangkat pada timestamp yang sama, dan perangkat berbeda tidak saling menimpa.

Checklist wajib:

```bash
php artisan migrate --force
```

Pastikan migrasi terbaru sudah masuk, termasuk unique key kombinasi:

- `kebun`
- `recorded_at`

## 9. Operasional Service Lain (Opsional)

Tersedia command/testing lain:

```bash
php artisan mqtt:listen
node ingest-mqtt.js
node node-subscriber.js
```

Gunakan hanya untuk kebutuhan khusus atau debugging.
Jangan dijalankan bersamaan dengan service production utama jika tidak diperlukan.

## 10. Lokasi Log Penting

- `storage/logs/laravel.log`
- `storage/logs/mqtt-service.log`
- `storage/logs/mqtt-service-error.log`

Pantau log PM2:

```bash
pm2 logs hidroponik-mqtt --lines 200
```

## 11. Troubleshooting Cepat

Jika data log tidak update atau power consumption berhenti:

1. Cek service hidup: `pm2 status`
2. Cek log subscriber: `npm run prod:mqtt:logs`
3. Pastikan topic sesuai: `MQTT_TOPIC=hidroganik/+/publish`
4. Pastikan DB terkoneksi dan migrasi sukses: `php artisan migrate:status`
5. Pastikan hanya satu subscriber utama yang aktif.

Jika terjadi data ganda:

1. Stop semua subscriber selain yang utama.
2. Jalankan ulang subscriber utama via PM2.
3. Verifikasi data masuk per perangkat di halaman log.

## 12. Quick Command Reference

```bash
# Start production MQTT service
npm run prod:mqtt:up

# Restart service
npm run prod:mqtt:restart

# View logs
npm run prod:mqtt:logs

# Stop service
npm run prod:mqtt:down

# Save PM2 process list
npm run prod:mqtt:save
```
