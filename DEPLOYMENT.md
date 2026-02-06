# Deployment Guide — DigitalOcean LEMP Droplet

This guide covers deploying Beacon Audit to a DigitalOcean LEMP 1-Click droplet. The LEMP image comes with Nginx, MySQL, PHP 8.0, Certbot, Fail2ban, and UFW pre-configured. We'll upgrade PHP to 8.4 and ignore MySQL (this app uses SQLite).

## Prerequisites

- A DigitalOcean account
- A domain name (optional but recommended for SSL)
- Your Google PageSpeed Insights API key

## 1. Create the Droplet

- Go to **Marketplace** and select the **LEMP** 1-Click App
- Plan: **Basic $6/mo** (1 vCPU, 1 GB RAM, 25 GB SSD) — sufficient for SQLite + PHP-FPM
- Region: closest to your users
- Authentication: **SSH key** (recommended)

Wait 2–3 minutes after creation for the LEMP stack to finish initializing.

## 2. Initial Server Setup

SSH into your droplet:

```bash
ssh root@your-server-ip
```

The LEMP image already has UFW configured (SSH, HTTP, HTTPS allowed) and Fail2ban running. Create a non-root deploy user:

```bash
adduser deploy
usermod -aG sudo deploy
```

Copy your SSH key to the new user:

```bash
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy
```

Switch to the deploy user for the remaining steps:

```bash
su - deploy
```

## 3. Upgrade PHP to 8.4

The LEMP droplet ships with PHP 8.0. The app requires PHP 8.4:

```bash
sudo apt update && sudo apt upgrade -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-intl
```

Disable the old PHP-FPM and enable 8.4:

```bash
sudo systemctl stop php8.0-fpm
sudo systemctl disable php8.0-fpm
sudo systemctl enable php8.4-fpm
sudo systemctl start php8.4-fpm
```

Verify:

```bash
php -v   # Should show PHP 8.4.x
```

## 4. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php -- --install-dir=/tmp
sudo mv /tmp/composer.phar /usr/local/bin/composer
```

## 5. Install Node.js (for building Tailwind CSS)

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

## 6. Install Git

```bash
sudo apt install -y git
```

## 7. Deploy the Application

```bash
sudo mkdir -p /var/www/beaconaudit
sudo chown deploy:deploy /var/www/beaconaudit

cd /var/www/beaconaudit
git clone your-repo-url .
```

Install dependencies and build assets:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Create the storage directories and set permissions:

```bash
mkdir -p storage/logs storage/cache/twig
touch storage/database.sqlite

# PHP-FPM runs as www-data
sudo chown -R deploy:www-data /var/www/beaconaudit
sudo chmod -R 775 storage/
```

## 8. Configure Environment

```bash
nano .env
```

Set the following values:

```env
APP_ENV=production
APP_DEBUG=false
DB_PATH=/var/www/beaconaudit/storage/database.sqlite
PAGESPEED_API_KEY=your-api-key-here
```

## 9. Configure Nginx

The LEMP droplet has Nginx pre-installed with a default site. Replace it with the Beacon Audit config:

```bash
sudo nano /etc/nginx/sites-available/beaconaudit
```

Paste the following (replace `your-domain.com` with your domain or server IP):

```nginx
server {
    listen 80;
    server_name your-domain.com;

    root /var/www/beaconaudit/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Block access to dotfiles
    location ~ /\. {
        deny all;
    }

    # Static files
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    # Route everything through index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Enable the site, remove the LEMP default, and reload:

```bash
sudo ln -s /etc/nginx/sites-available/beaconaudit /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/digitalocean
sudo nginx -t
sudo systemctl reload nginx
```

## 10. SSL with Let's Encrypt

Certbot is pre-installed on the LEMP droplet:

```bash
sudo certbot --nginx -d your-domain.com
```

Certbot will auto-configure Nginx for HTTPS and set up auto-renewal.

## 11. Create the Admin User

```bash
cd /var/www/beaconaudit
php cli/create-user.php --email=admin@example.com --password=your-secure-password --role=admin
```

## 12. Set Up Cron Jobs

```bash
crontab -e
```

Add the scheduled audit runner. This runs every 15 minutes and lets the `ScheduledAuditRunner` decide which URLs are due:

```cron
*/15 * * * * cd /var/www/beaconaudit && /usr/bin/php cron/run-scheduled-audits.php >> storage/logs/cron.log 2>&1
```

Verify it's registered:

```bash
crontab -l
```

## 13. SQLite Backups

SQLite is a single file, so backups are straightforward. Add a daily backup cron with a cleanup that keeps only the last 7 days:

```cron
0 2 * * * cp /var/www/beaconaudit/storage/database.sqlite /var/www/beaconaudit/storage/database-backup-$(date +\%Y\%m\%d).sqlite
0 3 * * * find /var/www/beaconaudit/storage/ -name "database-backup-*.sqlite" -mtime +7 -delete
```

For offsite backups, consider syncing to a DigitalOcean Space or S3 bucket:

```cron
0 3 * * * /usr/local/bin/aws s3 cp /var/www/beaconaudit/storage/database.sqlite s3://your-bucket/backups/database-$(date +\%Y\%m\%d).sqlite
```

## 14. Log Rotation

The cron log will grow over time. Set up logrotate to manage it automatically:

```bash
sudo nano /etc/logrotate.d/beaconaudit
```

Paste the following:

```
/var/www/beaconaudit/storage/logs/cron.log {
    weekly
    rotate 4
    compress
    missingok
    notifempty
}
```

This rotates the log weekly, keeps 4 weeks of compressed history, and ignores missing or empty files.

## Deploying Updates

When you push new changes:

```bash
cd /var/www/beaconaudit
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
sudo systemctl reload php8.4-fpm
```

Migrations run automatically on the next request (via `MigrationRunner` in `index.php`).

## Troubleshooting

| Issue | Check |
|-------|-------|
| 502 Bad Gateway | `sudo systemctl status php8.4-fpm` — is PHP-FPM running? Check the socket path matches the Nginx config |
| Permission denied on SQLite | `ls -la storage/` — www-data needs write access to `storage/` |
| Blank page | Check `storage/logs/` and `/var/log/nginx/error.log` |
| Cron not running | `grep CRON /var/log/syslog` and check `storage/logs/cron.log` |
| CSS missing | Did you run `npm run build`? Check `public/css/app.css` exists |
| Old PHP still active | Verify with `php -v` and check Nginx is pointing to the `php8.4-fpm.sock` |

## Server Maintenance

```bash
# Check disk space (SQLite + backups can grow)
df -h

# View PHP-FPM status
sudo systemctl status php8.4-fpm

# View Nginx status
sudo systemctl status nginx

# Renew SSL (auto-renewal should handle this, but to test)
sudo certbot renew --dry-run

# View Fail2ban status (pre-installed on LEMP droplet)
sudo fail2ban-client status
```
