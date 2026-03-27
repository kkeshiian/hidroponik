# MQTT Data Ingestion Service - Hidroponik

Background service untuk menerima dan menyimpan data sensor secara otomatis, **meskipun website ditutup atau browser tidak aktif**.

## 📋 Fitur

- ✅ Menerima data MQTT secara real-time 24/7
- ✅ Menyimpan data ke database MySQL otomatis
- ✅ Berjalan sebagai background service menggunakan Node.js
- ✅ Auto-reconnect jika koneksi terputus
- ✅ Mendukung konfigurasi interval penyimpanan dari web interface
- ✅ Logging lengkap untuk monitoring
- ✅ Kalibrasi otomatis (TDS & Suhu)
- ✅ Publish preview data untuk halaman kalibrasi

## 🚀 Quick Start

### 1. Install Dependencies

```bash
npm install
```

### 2. Konfigurasi .env

Pastikan file `.env` sudah berisi konfigurasi MQTT:

```env
# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hidroponik_db
DB_USERNAME=root
DB_PASSWORD=

# MQTT Configuration
MQTT_BROKER=broker.emqx.io
MQTT_PORT=1883
MQTT_USERNAME=
MQTT_PASSWORD=
MQTT_TOPIC=hidroganik/+/publish
```

### 3. Jalankan Service

```bash
npm run mqtt:start
```

Atau langsung:

```bash
node ingest-mqtt.js
```

Output yang akan muncul:

```
============================================================
  MQTT Data Ingestion Service - Hidroponik System
============================================================

✓ Database connected: hidroponik_db@127.0.0.1
✓ Save interval: Realtime
✓ MQTT connected: broker.emqx.io:1883
✓ Client ID: hidroponik_ingest_a1b2c3d4
✓ Subscribed to: hidroganik/+/publish

Service is running... Press CTRL+C to stop
------------------------------------------------------------
→ Received from hidroganik/kebun-a/publish
→ Published preview to hidroganik/kebun-a/preview
✓ Saved: kebun-a | pH=6.5 TDS=850 Suhu=24.5°C
```

## 🔧 Cara Menjalankan di Background (Production)

### Windows - Menggunakan PM2

PM2 adalah process manager untuk Node.js yang sangat populer dan mudah digunakan.

#### Install PM2

```bash
npm install -g pm2
```

#### Start Service

```bash
pm2 start ingest-mqtt.js --name "hidroponik-mqtt"
```

#### Commands PM2

```bash
# Lihat status service
pm2 status

# Lihat logs real-time
pm2 logs hidroponik-mqtt

# Stop service
pm2 stop hidroponik-mqtt

# Restart service
pm2 restart hidroponik-mqtt

# Delete service
pm2 delete hidroponik-mqtt

# Save PM2 configuration untuk auto-start saat reboot
pm2 save
pm2 startup

# Lihat monitoring
pm2 monit
```

#### Auto-start saat Windows Startup

Setelah menjalankan `pm2 startup`, PM2 akan memberikan command yang harus dijalankan (sebagai administrator). Jalankan command tersebut, lalu:

```bash
pm2 save
```

Sekarang service akan otomatis berjalan setiap kali laptop dinyalakan!

### Linux - Menggunakan PM2 atau Systemd

#### Option 1: PM2 (Recommended)

Same as Windows above.

#### Option 2: Systemd Service

Buat file `/etc/systemd/system/hidroponik-mqtt.service`:

```ini
[Unit]
Description=Hidroponik MQTT Data Ingestion Service
After=network.target mysql.service

[Service]
Type=simple
User=your-username
WorkingDirectory=/path/to/hidroponik
ExecStart=/usr/bin/node ingest-mqtt.js
Restart=always
RestartSec=10
StandardOutput=append:/var/log/hidroponik-mqtt.log
StandardError=append:/var/log/hidroponik-mqtt-error.log

[Install]
WantedBy=multi-user.target
```

Enable dan start:

```bash
sudo systemctl enable hidroponik-mqtt
sudo systemctl start hidroponik-mqtt
sudo systemctl status hidroponik-mqtt
```

## ⚙️ Konfigurasi

### Interval Penyimpanan

Interval penyimpanan dikonfigurasi melalui **web interface** (halaman Pengaturan). Service akan otomatis membaca perubahan konfigurasi setiap 1 detik.

File konfigurasi: `storage/app/private/mqtt_save_interval.json`

Format:

```json
{
    "interval": "realtime"
}
```

Nilai interval:

- `"realtime"` - Simpan setiap data yang masuk
- `"5"` - Simpan setiap 5 menit
- `"10"` - Simpan setiap 10 menit
- `"15"` - Simpan setiap 15 menit
- `"30"` - Simpan setiap 30 menit
- `"60"` - Simpan setiap 1 jam
- dst...

### Environment Variables

Semua konfigurasi ada di file `.env`:

| Variable        | Default              | Deskripsi                |
| --------------- | -------------------- | ------------------------ |
| `DB_HOST`       | 127.0.0.1            | Host database MySQL      |
| `DB_PORT`       | 3306                 | Port database            |
| `DB_DATABASE`   | hidroponik_db        | Nama database            |
| `DB_USERNAME`   | root                 | Username database        |
| `DB_PASSWORD`   |                      | Password database        |
| `MQTT_BROKER`   | broker.emqx.io       | MQTT broker hostname     |
| `MQTT_PORT`     | 1883                 | MQTT broker port         |
| `MQTT_USERNAME` |                      | MQTT username (opsional) |
| `MQTT_PASSWORD` |                      | MQTT password (opsional) |
| `MQTT_TOPIC`    | hidroganik/+/publish | Topic untuk subscribe    |

## 📊 Monitoring & Logging

### Logs dengan PM2

```bash
# Lihat semua logs
pm2 logs hidroponik-mqtt

# Lihat 100 baris terakhir
pm2 logs hidroponik-mqtt --lines 100

# Follow logs real-time
pm2 logs hidroponik-mqtt --raw
```

### Log Format

Service menggunakan structured logging dengan format:

```
[YYYY-MM-DD HH:MM:SS] ICON Message
```

Icons:

- `✓` - Info (success)
- `⚠` - Warning
- `✗` - Error
- `→` - Debug

Example logs:

```
[2026-01-25 14:30:45] ✓ Database connected: hidroponik_db@127.0.0.1
[2026-01-25 14:30:45] ✓ Save interval: Realtime
[2026-01-25 14:30:45] ✓ MQTT connected: broker.emqx.io:1883
[2026-01-25 14:30:46] ✓ Subscribed to: hidroganik/+/publish
[2026-01-25 14:31:12] → Received from hidroganik/kebun-a/publish
[2026-01-25 14:31:12] ✓ Saved: kebun-a | pH=6.5 TDS=850 Suhu=24.5°C
```

## 🔍 Troubleshooting

### Service Tidak Berjalan

**1. Check dependencies**

```bash
npm install
```

**2. Check .env configuration**

Pastikan semua variable sudah terisi dengan benar.

**3. Test database connection**

```bash
mysql -h 127.0.0.1 -u root -p hidroponik_db
```

**4. Test MQTT connection**

Gunakan MQTT client tool atau script test:

```bash
node test_mqtt_subscribe.php
```

### Data Tidak Masuk Database

**1. Check MQTT topic**

Topic harus match dengan pattern: `hidroganik/[kebun]/publish`

Contoh topic:

- `hidroganik/kebun-a/publish`
- `hidroganik/kebun-b/publish`

**2. Check data format**

Data harus dalam format JSON:

```json
{
    "suhu": 27.5,
    "ph": 9.19,
    "tds": 2.57,
    "phVolt": 2.079,
    "tdsVolt": 0.0063
}
```

Catatan: `phVolt` dan `tdsVolt` boleh dikirim di payload, namun saat ini tidak disimpan ke database.

**3. Check interval setting**

Jika interval > realtime, data tidak langsung tersimpan. Cek halaman Pengaturan.

**4. Check logs**

```bash
pm2 logs hidroponik-mqtt
```

### Service Crash atau Restart Terus

**1. Check error logs**

```bash
pm2 logs hidroponik-mqtt --err
```

**2. Database connection issue**

- Pastikan MySQL running
- Check credentials di `.env`
- Check database exist

**3. MQTT broker unreachable**

- Check internet connection
- Try different broker
- Check firewall

## 💡 Tips & Best Practices

### Development

- Jalankan langsung dengan `node ingest-mqtt.js` untuk melihat logs
- Set interval ke `realtime` untuk testing
- Monitor logs untuk debugging

### Production

- Gunakan PM2 untuk process management
- Enable PM2 startup untuk auto-start
- Set interval sesuai kebutuhan (5-10 menit recommended)
- Setup log rotation di PM2
- Monitor service dengan `pm2 monit`

### Performance

- Database connection pool sudah optimized (5 connections)
- Calibration data di-cache 60 detik
- MQTT auto-reconnect jika terputus
- Graceful shutdown dengan CTRL+C

### Security

- Jangan commit file `.env` ke git
- Gunakan strong password untuk database
- Gunakan MQTT authentication jika memungkinkan
- Restrict database access hanya dari localhost

## 📝 Service Architecture

```
┌─────────────────┐
│  MQTT Broker    │
│ (broker.emqx.io)│
└────────┬────────┘
         │ TCP 1883
         │ Topics: hidroganik/kebun-a/publish
         │         hidroganik/kebun-b/publish
         ↓
┌─────────────────────────────────┐
│  ingest-mqtt.js (Node.js)       │
│  - Subscribe ke topic           │
│  - Parse & validate data        │
│  - Apply calibration            │
│  - Check save interval          │
│  - Publish preview data         │
│  - Save to database             │
└────────┬────────────────────────┘
         │
         ↓
┌─────────────────┐
│  MySQL Database │
│  - telemetries  │
│  - calibration  │
└─────────────────┘
```

## 🆘 Support

Jika ada masalah:

1. Check logs: `pm2 logs hidroponik-mqtt`
2. Check service status: `pm2 status`
3. Check database: `mysql -u root -p`
4. Check MQTT broker: test dengan script test
5. Restart service: `pm2 restart hidroponik-mqtt`

---

**Hidroponik Data Ingestion Service**  
Version 1.0 - January 2026
