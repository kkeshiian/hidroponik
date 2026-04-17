# Hidroponik Alfa - Termius Server Setup

Aplikasi web Laravel untuk monitoring sistem hidroponik realtime dengan telemetry dan power consumption tracking.

## Quick Start

### Prerequisites
```bash
PHP 8.2+ | Node 20+ | MySQL 8+ | PM2
```

### 1. Clone & Setup
```bash
cd /var/www/hidroponik
git clone <repo-url> .
cp .env.example .env
```

### 2. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
npm ci
php artisan key:generate
npm run build
```

### 3. Database & Migrate
```bash
php artisan migrate --force
php artisan optimize
```

### 4. Fix Permissions
```bash
sudo chown -R www-data:www-data /var/www/hidroponik
sudo find /var/www/hidroponik/storage -type d -exec chmod 775 {} \;
```

### 5. Configure .env
```env
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Makassar
DB_HOST=127.0.0.1
DB_DATABASE=hidroponik_db
DB_USERNAME=your_user
DB_PASSWORD=your_pass
MQTT_HOST=broker.emqx.io
```

### 6. Start Services with PM2
```bash
# Start artisan subscriber (main telemetry service)
pm2 start "php artisan mqtt:subscribe" --name hidroponik-mqtt --cwd /var/www/hidroponik
pm2 save
pm2 startup
```

### 7. Web Server (Nginx)
```nginx
server {
    listen 80;
    server_name domain.com;
    root /var/www/hidroponik/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

## Key Services

| Service | Command | Required |
|---------|---------|----------|
| Web App | Nginx/PHP-FPM | ✅ Yes |
| MQTT Sub | `php artisan mqtt:subscribe` | ✅ Yes (PM2) |
| Database | MySQL | ✅ Yes |

## Common Commands

```bash
# View logs
pm2 logs hidroponik-mqtt

# Restart app
pm2 restart hidroponik-mqtt

# Rebuild assets
npm run build

# Clear cache
php artisan optimize:clear && php artisan optimize

# Export telemetry data
curl http://domain.com/api/logs/export
```

## File Structure
```
/var/www/hidroponik/
├── app/           (PHP/Laravel logic)
├── public/        (Web root - served by Nginx)
├── resources/     (Views/frontend)
├── database/      (Migrations)
├── storage/       (Logs/cache - needs write permission)
└── .env           (Configuration)
```

## Troubleshooting

**No data appearing?**
- Check MQTT service: `pm2 logs hidroponik-mqtt`
- Verify broker connection in `.env`

**Permission denied?**
- Run: `sudo chown -R www-data:www-data /var/www/hidroponik`

**502 Bad Gateway?**
- Check PHP-FPM socket path in Nginx config
- Restart: `sudo systemctl restart php8.2-fpm`

## Support
For issues, check logs via Termius:
```bash
tail -f /var/www/hidroponik/storage/logs/laravel.log
pm2 logs
```
