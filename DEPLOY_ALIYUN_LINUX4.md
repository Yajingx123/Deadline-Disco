# Deploy Guide (Alibaba Cloud Linux 4)

Target server: `118.31.127.110`

## 1) Install dependencies

```bash
sudo dnf update -y
sudo dnf install -y git nginx php php-fpm php-mysqlnd php-json php-mbstring mysql
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo dnf install -y nodejs
```

## 2) Prepare project

```bash
cd /var/www
sudo git clone <your-repo-url> Deadline-Disco
cd Deadline-Disco
cp .env.prod.example .env.production
```

Edit `.env.production`:

- `APP_URL=http://118.31.127.110`
- `DB_*` to your production DB
- `ALLOWED_ORIGINS=http://118.31.127.110`
- `REALTIME_ALLOWED_ORIGINS=http://118.31.127.110`
- `ZEGO_APP_ID` / `ZEGO_SERVER_SECRET`

## 3) Initialize database

```bash
mysql -u root -p < sql/101_acadbeat_core_tables.sql
mysql -u root -p < sql/102_acadbeat_core_seed_data.sql
mysql -u root -p < sql/105_academic_practice_video_match_tables.sql
```

## 4) Build frontend and start realtime

```bash
bash ./start_prod_linux.sh
```

## 5) Configure Nginx + PHP-FPM

```bash
sudo cp deploy/aliyun/nginx-acadbeat.conf.example /etc/nginx/conf.d/acadbeat.conf
sudo nginx -t
sudo systemctl enable --now nginx
sudo systemctl enable --now php-fpm
sudo systemctl restart nginx php-fpm
```

## 6) Optional: systemd for realtime

```bash
sudo cp deploy/aliyun/acadbeat-realtime.service.example /etc/systemd/system/acadbeat-realtime.service
sudo systemctl daemon-reload
sudo systemctl enable --now acadbeat-realtime
sudo systemctl status acadbeat-realtime
```

## 7) Security group

Open inbound:

- `22` (SSH)
- `80` (HTTP)
- `443` (HTTPS, when enabled)

Keep `3001` private (do not expose publicly).
