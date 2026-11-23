# GateAI 水电站阀门智能调度系统

前后端分离系统：前端 Vue 3 + Vite + TypeScript，后端 Laravel 10 + MySQL，实时推送使用 Laravel Reverb WebSocket，文件（头像等）使用阿里云 OSS。

本文档面向**宝塔面板（BT Panel）生产部署**，按本文操作可以避开本项目已经遇到过的部署问题。

## 1. 项目组成

| 路径 | 说明 |
| --- | --- |
| `GateAI/` | Laravel 10 后端，Web 根目录必须是 `GateAI/public` |
| `fronted/Water-conservancy/` | Vue 3 + Vite 前端工程 |
| `fronted/Water-conservancy/dist/` | 前端构建产物（本地生成，不入库） |
| `dist/` | 仓库根目录的构建产物副本（本地产物，不入库） |
| `GateAI.zip` | 后端压缩包（本地产物，不入库） |

`dist/` 与 `GateAI.zip` 已在根目录 `.gitignore` 中忽略，不要通过 Git 上传。

## 2. 推荐部署架构

推荐**前后端同域名**，由 Nginx 统一入口，避免 CORS、HTTPS 混合内容等问题：

```
浏览器
  │ HTTPS 443
  ▼
Nginx（宝塔站点，根目录指向前端 dist）
  ├── /                    → 前端静态文件（SPA 回退到 index.html）
  ├── /api/                → 127.0.0.1:8089（Laravel）
  ├── /broadcasting/       → 127.0.0.1:8089（广播鉴权）
  ├── /app、/apps          → 127.0.0.1:8080（Reverb WebSocket）
  │
  └── Laravel 后端站点（8089，仅本机）
        ├── queue worker（队列常驻进程）
        └── reverb:start（WebSocket 常驻进程）
```

前端 `.env.production` 使用相对路径 `VITE_API_BASE_URL=/api`，由 Nginx 反向代理到后端。

## 3. 环境要求

- **PHP >= 8.1**（建议 8.2），扩展：`fileinfo`、`pdo_mysql`、`mbstring`、`openssl`、`gd`、`zip`、`xml`、`bcmath`、`curl`
- Composer 2
- MySQL 5.7+ / 8.0（字符集 `utf8mb4`）
- Nginx 1.20+
- Node.js **20.19+ 或 22 LTS+**（Vite 8 要求），npm 10+
- 宝塔“进程守护管理器”（Supervisor）
- 阿里云 OSS Bucket（头像上传依赖）
- 如需实时推送：PHP CLI 需要 `pcntl` 扩展

## 4. 后端部署（Laravel）

### 4.1 上传代码并安装依赖

```bash
cd /www/wwwroot/GateAI/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env      # 如果 .env 不存在
```

> `.env.example` 中的数据库配置是历史示例，**禁止直接用于生产**，请按下文重新填写。

### 4.2 配置 `.env`

关键配置示例（值按实际环境替换）：

```dotenv
APP_NAME=GateAI
APP_ENV=production
APP_DEBUG=false
APP_URL=https://water.example.com

# 数据库（与后端同机时使用 127.0.0.1）
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gateai
DB_USERNAME=gateai
DB_PASSWORD=请填写强密码

# 队列与广播
QUEUE_CONNECTION=database
BROADCAST_DRIVER=reverb
CACHE_DRIVER=file
SESSION_DRIVER=file

# Reverb：服务端向 Reverb 发布消息，走本机回环
REVERB_APP_ID=请填写
REVERB_APP_KEY=请填写
REVERB_APP_SECRET=请填写
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

# 阿里云 OSS（头像上传必须，AppServiceProvider 启动时会强制加载 oss 磁盘）
OSS_ACCESS_KEY_ID=请填写
OSS_ACCESS_KEY_SECRET=请填写
OSS_BUCKET=请填写
OSS_ENDPOINT=请填写
OSS_PREFIX=exports

# 天气服务等第三方配置按需填写
CAIYUN_TOKEN=请填写
```

首次部署如果 `APP_KEY` 或 `JWT_SECRET` 为空，执行：

```bash
php artisan key:generate
php artisan jwt:secret
```

### 4.3 初始化数据库

```bash
php artisan migrate --force

# 仅首次部署需要：导入角色、字典、设备、演示数据
php artisan db:seed --force
```

默认账号见 `database/seeders/UserSeeder.php`，**上线后立即修改密码**。

如使用本地公开磁盘（本项目头像走 OSS，此项可选）：

```bash
php artisan storage:link
```

### 4.4 目录权限

宝塔 PHP 运行用户通常是 `www`：

```bash
chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
chmod 600 .env
```

### 4.5 宝塔站点配置

- 添加站点，**运行目录选择 `/public`**（即 `GateAI/public`）
- PHP 版本选择 8.2
- 伪静态选择 `laravel5`，或使用：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

站点配置参考（宝塔会自动生成 PHP-FPM 配置，以下仅示意）：

```nginx
server {
    listen 8089;
    server_name _;
    root /www/wwwroot/GateAI/backend/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/tmp/php-cgi-82.sock;   # 以宝塔实际 PHP-FPM socket 为准
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

> 不要把站点根目录设置为 `GateAI/` 项目根目录，否则 `.env`、`storage` 存在被直接访问的风险。

### 4.6 PHP 配置注意事项

- 宝塔默认的**“防跨站攻击(open_basedir)”**可能拦截 Composer / artisan / vendor 访问，必要时关闭或加入项目路径。
- 宝塔默认**禁用函数**可能包含 `proc_open`、`putenv`、`pcntl_*`。Composer 安装和 Reverb 启动如果报错，先检查这里。
- 建议调整：

```ini
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
memory_limit = 256M
```

- PHP-FPM 的 `request_terminate_timeout` 建议不低于 300s，避免 AI 推理类接口被提前终止。

### 4.7 常驻进程（必须）

当前配置为 `QUEUE_CONNECTION=database` + `BROADCAST_DRIVER=reverb`。**缺少队列进程时，告警、仿真进度等事件只会写数据库，不会推送前端。**

在宝塔“进程守护管理器”中新建两个进程（PHP 路径按实际版本替换）：

**队列 worker**

```bash
/www/server/php/82/bin/php /www/wwwroot/GateAI/backend/artisan queue:work --sleep=3 --tries=3 --timeout=600
```

**Reverb WebSocket**

```bash
/www/server/php/82/bin/php /www/wwwroot/GateAI/backend/artisan reverb:start --host=127.0.0.1 --port=8080
```

要求：

- 运行用户 `www`，进程数量 1，开机自启、异常自动重启
- Reverb 只监听 `127.0.0.1:8080`，由 Nginx 对外提供 WSS，不要直接把 8080 暴露到公网
- **每次后端代码更新后重启这两个进程**，否则会继续跑旧代码
- 使用宝塔命令行时注意 PHP CLI 版本，务必用绝对路径，避免和站点 PHP 版本不一致

### 4.8 实时推送 / WSS 配置

- 后端 `.env` 中 `REVERB_HOST/REVERB_PORT/REVERB_SCHEME` 是**服务端发布消息**用的，建议指本机 `127.0.0.1:8080`
- 前端 `VITE_REVERB_*` 是**浏览器连接**用的，HTTPS 站点必须是 `wss://域名:443`
- Nginx 增加 `/app` 反向代理（见第 5.3 节），并配置 `Upgrade` / `Connection` 头
- 私有频道鉴权地址：后端 [BroadcastServiceProvider.php](GateAI/app/Providers/BroadcastServiceProvider.php) 注册的是 `/broadcasting/auth`，前端当前拼接的是 `${VITE_API_BASE_URL}/broadcasting/auth`（即 `/api/broadcasting/auth`）。如果私有频道鉴权返回 401/404，优先检查这里：在 Nginx 同时代理 `/broadcasting/`，或调整前端 `authEndpoint` / 后端广播路由前缀，保持一致。

## 5. 前端部署

### 5.1 配置生产环境变量

编辑 `fronted/Water-conservancy/.env.production`：

```dotenv
# 同域名反代（推荐）
VITE_API_BASE_URL=/api
VITE_API_V1_PREFIX=/v1
VITE_SIMULATION_MOCK=false

# 使用 Reverb 时填写；不填则退回普通 WebSocket
VITE_REVERB_APP_KEY=与后端 REVERB_APP_KEY 一致
VITE_REVERB_HOST=water.example.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

注意：

- **Vite 环境变量在构建时写入产物**，修改后必须重新构建，只改服务器上的文件无效。
- 仓库根目录现有的 `dist/` 是按历史 `.env.production` 构建的（指向 `http://47.108.169.152:8089/api`），**不要直接拿去部署**，请按生产环境重新构建。
- 如果前后端分开域名，`VITE_API_BASE_URL` 填完整地址（如 `https://api.example.com/api`），并确保前后端都是 HTTPS，否则浏览器会拦截混合内容。

### 5.2 构建

```bash
cd fronted/Water-conservancy
npm ci
npm run build:dist
```

产物在 `fronted/Water-conservancy/dist/`，将**目录内所有文件**上传到前端站点根目录。

如果服务器没有 Node，可以在本地构建后上传 `dist` 内容。

### 5.3 前端 Nginx 配置

```nginx
server {
    listen 443 ssl http2;
    server_name water.example.com;
    root /www/wwwroot/GateAI/dist;
    index index.html;
    client_max_body_size 50m;

    gzip on;
    gzip_min_length 1k;
    gzip_comp_level 6;
    gzip_types text/plain text/css application/javascript application/json image/svg+xml;

    # SPA history 路由回退，刷新子路由不 404
    location / {
        try_files $uri $uri/ /index.html;
    }

    # 普通 API 请求
    location ^~ /api/ {
        proxy_pass http://127.0.0.1:8089;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300s;
    }

    # 普通 WebSocket 降级通道（未启用 Reverb 时使用）
    location = /api/v1/simulation/stream-data {
        proxy_pass http://127.0.0.1:8089;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 3600s;
    }

    # 广播鉴权
    location ^~ /broadcasting/ {
        proxy_pass http://127.0.0.1:8089;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Reverb WebSocket（/app/{key}、/apps/...）
    location ^~ /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 3600s;
    }

    # 带哈希的静态资源长缓存
    location ~* \.(?:js|css|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf|glb|gltf)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # 入口文件不缓存，保证发版后立即生效
    location = /index.html {
        add_header Cache-Control "no-store";
    }
}
```

> 如果使用宝塔“反向代理”功能添加 `/api`，请确认保留原始 URI（代理地址末尾不要多加 `/`），否则会出现 `/api/api/...` 或 404。

### 5.4 子路径部署

如果前端需要部署到 `https://example.com/water/` 这类子路径，必须同时调整：

- `vite.config.ts` 的 `base`
- Vue Router 的 `createWebHistory` base
- Nginx `try_files` 与静态资源路径

默认配置只适用于**域名根路径**部署。

## 6. 宝塔配置清单

- [ ] PHP 8.1+，已安装 `fileinfo / pdo_mysql / mbstring / openssl / gd / zip / xml / bcmath`
- [ ] 后端站点运行目录为 `GateAI/public`
- [ ] 后端伪静态为 Laravel 规则
- [ ] 前端站点根目录为 `dist`，SPA 回退到 `index.html`
- [ ] 已关闭 `APP_DEBUG`，已配置 `APP_ENV=production`
- [ ] `.env` 权限 600，且不在 Web 根目录下
- [ ] `storage`、`bootstrap/cache` 可写
- [ ] 已执行 `migrate`，首次部署已执行 `db:seed`
- [ ] 已配置并启动队列 worker
- [ ] 已配置并启动 Reverb
- [ ] Nginx 已代理 `/api`、`/broadcasting`、`/app`
- [ ] 已配置 HTTPS，前端与接口均为 HTTPS/WSS
- [ ] `client_max_body_size`、`upload_max_filesize`、`post_max_size` 不小于 50M
- [ ] 云安全组 / 宝塔防火墙只开放 80、443；3306、8080、8089 不对公网开放
- [ ] 已检查宝塔“防跨站 open_basedir”和“禁用函数”
- [ ] 已确认 `OSS_ACCESS_KEY_ID / OSS_ACCESS_KEY_SECRET / OSS_BUCKET / OSS_ENDPOINT` 已配置

## 7. 部署后验证

1. 访问后端健康接口或登录接口，确认返回 JSON 而不是 404/500
2. 打开前端登录页，确认登录成功并跳转
3. 刷新 `/dashboard/overview` 等子路由，确认不出现 404
4. 上传头像，确认 OSS 配置正确且没有 413（文件大小超限）
5. 打开数字孪生/实时页面，确认 WebSocket 连接成功
6. 触发一次告警或仿真，确认前端能实时收到推送（验证队列 + Reverb）
7. 检查 `GateAI/storage/logs/laravel.log` 与 Nginx 错误日志无异常

## 8. 常见问题

| 现象 | 可能原因 | 处理 |
| --- | --- | --- |
| 刷新子路由 404 | SPA 未配置回退 | 增加 `try_files $uri $uri/ /index.html;` |
| 接口 404 | 反向代理路径拼接错误 | 检查 `proxy_pass` 末尾是否多 `/`，确认后端路由带 `/api` |
| 登录 401 | Token / JWT 配置问题 | 检查 `JWT_SECRET`、Authorization 头、时间同步 |
| 跨域报错 | 分域名部署未放开 CORS | 配置 `config/cors.php` 的 `allowed_origins`，或改为同域名反代 |
| 上传 413 | Nginx/PHP 上传限制过小 | 调大 `client_max_body_size`、`upload_max_filesize`、`post_max_size` |
| 上传头像失败 | OSS 配置缺失或权限不足 | 补齐 `OSS_*` 环境变量，检查 Bucket 权限 |
| 部署后 500 | 权限 / 缓存 / 环境变量问题 | 检查 `storage` 权限、`laravel.log`、`APP_KEY`、`APP_DEBUG` |
| 改了 `.env` 不生效 | 已执行 `config:cache` | 重新执行 `php artisan config:cache` 或先 `config:clear` |
| 告警/进度不实时 | 队列 worker 未启动 | 启动并检查 Supervisor 日志 |
| WebSocket 连不上 | HTTPS 页面连 `ws://`、端口未代理 | 使用 `wss://` + `/app` 反代，确认 8080 正常 |
| 私有频道 401/404 | 广播鉴权路径不一致 | 对齐 `/broadcasting/auth` 与 `/api/broadcasting/auth` |
| Composer/artisan 报错 | 命令行 PHP 版本或禁用函数 | 使用绝对 PHP 路径，检查 `open_basedir`、`proc_open` |
| 前端请求超时 | 接口耗时超过 axios 30s | 调大 axios 超时、`fastcgi_read_timeout`、PHP-FPM 超时 |

## 9. 安全注意事项

- **立即更换数据库密码**：历史版本 `.env.example` 中曾包含真实数据库地址与账号密码并提交过仓库，生产环境务必使用全新的独立账号和强密码。
- 生产环境必须 `APP_DEBUG=false`、`APP_ENV=production`。
- `.env` 不要放在 Web 根目录，权限设置为 600。
- MySQL 3306、Redis 6379、Reverb 8080、后端 8089 仅监听本机或内网，不要暴露公网。
- OSS AccessKey 使用最小权限子账号，不要使用主账号 AccessKey。
- 生产 CORS 建议收敛到前端域名，而不是 `*`（见 `GateAI/config/cors.php`）。
- 全站启用 HTTPS，HTTP 强制跳转 HTTPS。
- `dist/`、`GateAI.zip` 属于构建产物，已在 `.gitignore` 中忽略，不要提交到仓库。
