# SmartGym Production Deployment Guide

## Recommended architecture

Para sa kasalukuyang codebase, **VPS o Render Web Service ang pinakasimple** dahil ang SmartGym ay isang combined Express + Vite application. Ang Express server ang nagsisilbing REST API at production static server. Kailangan din nito ng MySQL-compatible database gaya ng PlanetScale, Aiven MySQL, Railway MySQL, o ibang managed MySQL provider.

Ang Vercel ay posible, pero kailangan munang gawing serverless ang API routes. Hindi direktang tatakbo sa Vercel ang kasalukuyang long-running `server/index.js` bilang standard Vercel Function nang walang refactor.

## Option A — VPS with Ubuntu/Nginx/PM2

### 1. Ihanda ang server

```bash
sudo apt update
sudo apt install -y git nginx curl
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
sudo npm install -g pm2
```

### 2. I-download ang project

```bash
git clone <YOUR_REPOSITORY_URL> /var/www/smartgym
cd /var/www/smartgym
npm ci
```

### 3. I-set ang database environment variable

```bash
nano .env
```

```env
NODE_ENV=production
PORT=3000
DATABASE_URL=mysql://DB_USER:DB_PASSWORD@DB_HOST:3306/DB_NAME
```

Huwag i-commit ang `.env` file.

### 4. Ihanda ang database at build

```bash
npm run db:seed
npm run build
pm2 start npm --name smartgym -- run start
pm2 save
pm2 startup
```

I-check ang local service:

```bash
curl http://127.0.0.1:3000/api/health
```

### 5. I-configure ang Nginx

```nginx
server {
    listen 80;
    server_name smartgym.example.com;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Pagkatapos:

```bash
sudo nginx -t
sudo systemctl reload nginx
sudo snap install --classic certbot
sudo certbot --nginx -d smartgym.example.com
```

## Option B — Render Web Service

1. I-push ang project sa GitHub.
2. Sa Render, gumawa ng **New → Web Service** at piliin ang repository.
3. Gamitin ang mga value na ito:

| Setting | Value |
| --- | --- |
| Runtime | Node |
| Build command | `npm ci && npm run build` |
| Start command | `npm run start` |
| Health check path | `/api/health` |
| Region | pinakamalapit sa users at database |

4. Idagdag sa Render Environment Variables:

```env
NODE_ENV=production
PORT=3000
DATABASE_URL=mysql://DB_USER:DB_PASSWORD@DB_HOST:3306/DB_NAME
```

5. Pagkatapos ng unang deploy, patakbuhin ang database schema sa database provider gamit ang `server/schema.sql`, o gumamit ng one-off shell command:

```bash
npm run db:seed
```

6. I-test ang:

```text
https://YOUR-RENDER-SERVICE.onrender.com/api/health
```

Tandaan: ang Render Web Service ay hindi sapat na database. Kailangan pa rin ng hiwalay na MySQL-compatible database. Kung gagamit ng Render Postgres, kailangang i-convert ang schema at database driver dahil MySQL syntax ang kasalukuyang implementation.

## Option C — Vercel

### Frontend-only deployment

Kung frontend lamang ang ide-deploy, gamitin:

```bash
npm run build
```

- Framework preset: Vite
- Build command: `npm run build`
- Output directory: `dist`

Ngunit hindi kasama dito ang Express API at RFID endpoint.

### Full-stack Vercel deployment

Para sa full-stack Vercel deployment, kailangan:

1. Ilipat ang bawat API route sa `api/` serverless functions.
2. Gumamit ng Vercel-compatible MySQL provider.
3. Iwasang umasa sa in-memory demo arrays dahil nawawala ang state sa serverless restart.
4. Ilipat ang database queries sa shared service/repository layer.
5. Gumawa ng `vercel.json` para sa SPA fallback at `/api/*` routing.

Sa production recommendation, gamitin muna ang **Render o VPS** para tumakbo ang kasalukuyang package nang walang malaking code refactor.

## Production checklist

- Gumamit ng real authentication at role authorization bago public launch.
- Huwag gamitin ang demo fallback arrays bilang permanent database.
- Palitan ang sample RFID UIDs.
- I-set ang strict CORS sa actual frontend domain.
- Gumamit ng HTTPS dahil kailangang secure ang RFID/device traffic at future session cookies.
- I-backup ang database bago mag-migrate.
- I-monitor ang `/api/health`, API logs, disk, memory, at database connections.
