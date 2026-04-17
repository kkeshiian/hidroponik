# 🚀 Deployment Guide: Kebun Normalization Fix

**Status:** ✅ Code ready - Commit: `a31d872`

## Problem Solved

Data `kebun-1` dari MQTT masuk tapi tidak muncul di UI karena:

- MQTT topic menggunakan `kebun-1`
- Database menyimpan sebagai `kebun-1`
- UI dashboard mencari `kebun-a` (standardized)
- **Result:** Data tidak ketemu ❌

## Solution Implemented

✅ Added `normalizeKebun()` method di MqttSubscribe.php

- Automatically converts `kebun-1` → `kebun-a`
- Converts `kebun-2` → `kebun-b`
- Before storing to database

## Deployment Steps untuk Termius Hosting

### Step 1: SSH ke Server Termius

```bash
ssh user@your-termius-ip
cd /path/to/hidroponik
```

### Step 2: Pull Latest Code

```bash
git fetch origin
git reset --hard origin/main
# atau jika pakai branch berbeda:
git reset --hard origin/htht
```

### Step 3: Stop Current MQTT Subscriber

```bash
# Kill the running mqtt:subscribe process
pkill -f "artisan mqtt:subscribe"

# Verify it's stopped
ps aux | grep mqtt:subscribe
```

### Step 4: Restart MQTT Subscriber dengan Code Baru

```bash
php artisan mqtt:subscribe &
# atau pakai nohup agar tetap jalan setelah disconnect SSH:
nohup php artisan mqtt:subscribe > storage/logs/mqtt.log 2>&1 &
```

### Step 5: Verify in UI

1. Buka http://hidroganikalfa.web.id/log
2. Kirim test data MQTT:
    ```
    Topic: hidroganik/kebun-1/publish
    Payload: {"ph": 6.5, "tds": 850, "suhu": 25}
    ```
3. Lihat di Log Data → harusnya muncul sebagai **kebun-a** ✓

---

## 📋 What Changed in Code

### File: `app/Console/Commands/MqttSubscribe.php`

**New Method Added:**

```php
protected function normalizeKebun(string $kebun): string
{
    $value = strtolower(trim($kebun));

    if (in_array($value, ['kebun-1', 'kebun-a', 'a'], true)) {
        return 'kebun-a';
    }

    if (in_array($value, ['kebun-2', 'kebun-b', 'b'], true)) {
        return 'kebun-b';
    }

    return strtolower($kebun);
}
```

**Updated in `handlePublishMessage()` method:**

```php
// BEFORE:
$rawPayload = [
    'kebun' => $kebun,  // ❌ Raw dari MQTT
    ...
];

// AFTER:
$normalizedKebun = $this->normalizeKebun($kebun);  // ✅ Normalized
$rawPayload = [
    'kebun' => $normalizedKebun,  // ✅ Standardized name
    ...
];
```

**Updated `resolveMirrorDevice()`:**

```php
// Returns normalized names
'kebun-1' → 'kebun-b'  (instead of kebun-2)
'kebun-2' → 'kebun-a'  (instead of kebun-1)
```

---

## 🧪 Testing Checklist

- [ ] Git pull successful
- [ ] Old MQTT process killed
- [ ] New MQTT process started
- [ ] Check logs: `tail -f storage/logs/mqtt.log`
- [ ] Send test MQTT message
- [ ] Verify data appears in Log Data UI as `kebun-a`
- [ ] Check database: Data saved with normalized name
    ```sql
    SELECT DISTINCT kebun FROM telemetries;
    -- Should show: kebun-a, kebun-b (no kebun-1 or kebun-2)
    ```

---

## 📊 Database Verification

Setelah deployment, verify di database:

```bash
# SSH ke server, masuk MySQL
mysql -u user -p database_name

# Query distinct kebun values
SELECT DISTINCT kebun FROM telemetries ORDER BY kebun;

# Expected output:
# kebun-a
# kebun-b
# (no raw names like kebun-1 or kebun-2 anymore)
```

---

## ⚠️ Troubleshooting

### Data masih tidak muncul?

1. Periksa MQTT subscriber berjalan:

    ```bash
    ps aux | grep mqtt:subscribe
    ```

2. Lihat error logs:

    ```bash
    tail -50 storage/logs/laravel.log
    tail -50 storage/logs/mqtt.log
    ```

3. Verify MySQL connection:
    ```bash
    php artisan tinker
    > DB::connection()->getPdo()
    ```

### Old data with kebun-1 masih ada?

Opsi 1: Keep as-is (UI filter akan handle)
Opsi 2: Migrate with script:

```bash
php artisan migrate:fresh  # ⚠️ WARNING: deletes all data!
# atau
php artisan db:seed  # jika ada seeder untuk test data
```

---

## 📝 Commit Information

```
Commit: a31d872
Author: [Your Name]
Message: fix: normalize kebun-1 to kebun-a before saving to database

Changes:
- Add normalizeKebun() method
- Update handlePublishMessage() to normalize kebun
- Update resolveMirrorDevice() to return normalized names
```

---

## ✅ Deployment Completion

Setelah semua steps selesai:

- ✓ Data kebun-1 dari MQTT akan disimpan sebagai kebun-a
- ✓ Data muncul di UI Log Data dengan alias kebun-a
- ✓ Dashboard card akan menampilkan data kebun-a dengan benar
