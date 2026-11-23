# GateAI 部署配置（8.156.80.227）

| 服务 | 地址 |
| --- | --- |
| 前端 | `http://8.156.80.227:6308` |
| 后端 | `http://8.156.80.227:6208` |
| API | `http://8.156.80.227:6208/api` |

## 一、后端 Nginx 配置文件（6208）

```nginx
server {
    listen 6208;
    server_name 8.156.80.227;

    # Laravel Web 根目录必须是 public
    root /www/wwwroot/GateAI/backend/public;
    index index.php index.html;

    client_max_body_size 50m;
    fastcgi_read_timeout 300s;

    access_log /www/wwwlogs/8.156.80.227_6208.log;
    error_log  /www/wwwlogs/8.156.80.227_6208.error.log;

    # Laravel 伪静态
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # 宝塔 PHP-FPM，PHP 版本按实际修改
    include enable-php-84.conf;

    # 禁止访问 .env、.git 等隐藏文件
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # 兼容前端拼接的广播鉴权地址
    location = /api/broadcasting/auth {
        rewrite ^ /broadcasting/auth last;
    }

    # CORS 由 Laravel config/cors.php 处理
    # 这里不要重复添加 Access-Control-Allow-Origin，避免出现 multiple values
}
```

## 二、后端伪静态

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 三、前端 Nginx 配置文件（6308）

```nginx
server
{
    listen 6308;
    server_name 8.156.80.227;
    index index.html;
    root /www/wwwroot/GateAI/fronted/dist;

    client_max_body_size 50m;

    #CERT-APPLY-CHECK--START
    # 用于SSL证书申请时的文件验证相关配置 -- 请勿删除
    include /www/server/panel/vhost/nginx/well-known/8.156.80.227_6308.conf;
    #CERT-APPLY-CHECK--END
    include /www/server/panel/vhost/nginx/extension/8.156.80.227_6308/*.conf;

    #SSL-START SSL相关配置，请勿删除或修改下一行带注释的404规则
    #error_page 404/404.html;
    #SSL-END

    #ERROR-PAGE-START  错误页配置，可以注释、删除或修改
    error_page 404 /404.html;
    #error_page 502 /502.html;
    #ERROR-PAGE-END

    #PHP-INFO-START
    # 前端是纯静态站点，不需要 PHP，已移除
    # include enable-php-84.conf;
    #PHP-INFO-END

    #REWRITE-START
    # 已改为在下面直接写 SPA 回退，避免和面板伪静态重复
    # 如果继续使用面板伪静态，请删除下面的 location /，并把 SPA 规则填到面板
    # include /www/server/panel/vhost/rewrite/8.156.80.227_6308.conf;
    #REWRITE-END

    # 禁止访问的敏感文件
    location ~* (\.user.ini|\.htaccess|\.htpasswd|\.env.*|\.project|\.bashrc|\.bash_profile|\.bash_logout|\.DS_Store|\.gitignore|\.gitattributes|LICENSE|README\.md|CLAUDE\.md|CHANGELOG\.md|CHANGELOG|CONTRIBUTING\.md|TODO\.md|FAQ\.md|composer\.json|composer\.lock|package(-lock)?\.json|yarn\.lock|pnpm-lock\.yaml|\.\w+~|\.swp|\.swo|\.bak(up)?|\.old|\.tmp|\.temp|\.log|\.sql(\.gz)?|docker-compose\.yml|docker\.env|Dockerfile|\.csproj|\.sln|Cargo\.toml|Cargo\.lock|go\.mod|go\.sum|phpunit\.xml|phpunit\.xml|pom\.xml|build\.gradl|pyproject\.toml|requirements\.txt|application(-\w+)?\.(ya?ml|properties))$
    {
        return 404;
    }

    # 禁止访问的敏感目录
    location ~* /(\.git|\.svn|\.bzr|\.vscode|\.claude|\.idea|\.ssh|\.github|\.npm|\.yarn|\.pnpm|\.cache|\.husky|\.turbo|\.next|\.nuxt|node_modules|runtime)/ {
        return 404;
    }

    #一键申请SSL证书验证目录相关设置
    location ~ \.well-known{
        allow all;
    }

    #禁止在证书验证目录放入敏感文件
    if ( $uri ~ "^/\.well-known/.*\.(php|jsp|py|js|css|lua|ts|go|zip|tar\.gz|rar|7z|sql|bak)$" ) {
        return 403;
    }

    # 静态资源缓存
    location ~* \.(gif|jpg|jpeg|png|bmp|swf|svg|webp|ico|woff|woff2|ttf|eot|glb|gltf|js|css)$
    {
        expires      30d;
        add_header   Cache-Control "public, immutable";
        error_log /dev/null;
        access_log /dev/null;
    }

    # 入口文件不缓存，保证发版后立即生效
    location = /index.html
    {
        add_header Cache-Control "no-store";
    }

    # SPA 前端路由回退
    location /
    {
        try_files $uri $uri/ /index.html;
    }

    # 跨域由后端 config/cors.php 处理
    # 这个前端静态站点不要添加 Access-Control-Allow-Origin，避免重复头

    access_log  /www/wwwlogs/8.156.80.227_6308.log;
    error_log  /www/wwwlogs/8.156.80.227_6308.error.log;
}
```

### 前端伪静态（使用宝塔面板伪静态时）

如果启用面板伪静态，把上面的 `location /` 删除，然后在面板伪静态中填：

```nginx
location / {
    try_files $uri $uri/ /index.html;
}
```

## 四、配套修改

前端 `.env.production`：

```dotenv
VITE_API_BASE_URL=http://8.156.80.227:6208/api
VITE_API_V1_PREFIX=/v1
VITE_SIMULATION_MOCK=false
```

改完重新构建并上传：

```bash
cd fronted/Water-conservancy
npm ci
npm run build:dist
```

后端 `GateAI/config/cors.php` 至少修改以下项：

```php
'paths' => [
    'api/*',
    'broadcasting/*',
    'sanctum/csrf-cookie',
],

'allowed_methods' => ['*'],

'allowed_origins' => [
    'http://8.156.80.227:6308',
],

'allowed_headers' => ['*'],

'max_age' => 86400,

// 使用 Bearer Token，不依赖 Cookie
'supports_credentials' => false,
```

Nginx 侧不要再添加 `Access-Control-Allow-Origin`，否则会出现重复响应头。

修改后执行：

```bash
cd /www/wwwroot/GateAI/backend
php artisan config:clear
php artisan config:cache
```

## 五、验证

```bash
curl -i "http://8.156.80.227:6208/api/auth/login" \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"account":"admin","password":"admin123"}'
```

```bash
curl -i -X OPTIONS "http://8.156.80.227:6208/api/auth/login" \
  -H "Origin: http://8.156.80.227:6308" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: authorization,content-type"
```

预检响应应包含：

```text
Access-Control-Allow-Origin: http://8.156.80.227:6308
```
