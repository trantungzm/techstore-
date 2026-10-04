Production run and PHP/OPcache recommendations
=============================================

Quick steps to run the Laravel admin service in production (Windows or Linux):

1) Use PHP-FPM + Nginx (recommended) or IIS with FastCGI on Windows.

Example Nginx site (replace paths):

```
server {
    listen 80;
    server_name localhost;
    root /path/to/services/php-admin-service/laravel/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000; # php-fpm
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

2) Enable OPcache in `php.ini` (recommended settings):

```
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.validate_timestamps=1 # set to 0 in production and restart on deploy
opcache.revalidate_freq=2
```

Dev dùng `opcache.validate_timestamps=1` để không cần restart PHP khi sửa code (chấp
nhận chậm hơn một chút để đổi lấy tiện dev). Production nên đặt `0` để tối ưu tốc độ
tối đa — đổi lại phải restart PHP-FPM (hoặc gọi `opcache_reset()`) sau **mỗi lần** deploy
code mới, nếu không code cũ vẫn được cache và chạy.

3) Laravel production optimizations (run on deploy — **bắt buộc**, không phải optional):

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer dump-autoload -o
```

Lý do bắt buộc: không cache, mỗi request phải parse lại toàn bộ `config/*.php` + `.env`
và đăng ký lại toàn bộ route — đo thực tế trên máy dev cho thấy đây là nguồn latency
đáng kể (xem benchmark trong `docs/PRE_DEPLOY_CHECKLIST.md`). Deploy script/CI phải chạy
lại các lệnh này sau **mỗi** lần deploy, không chỉ một lần thủ công.

**Khi debug sự cố production:** nếu nghi ngờ một biến `.env` vừa sửa không có tác dụng,
luôn chạy `php artisan config:clear` trước khi tiếp tục điều tra — giá trị đang chạy có
thể là snapshot từ lần `config:cache` gần nhất, không phải `.env` hiện tại trên đĩa. Nhớ
`config:cache` lại sau khi xong để không bỏ lỡ lợi ích hiệu năng.

4) Filesystem permissions: ensure the web user can write to `storage/` and `bootstrap/cache/`.

5) Monitoring: enable slow-request logging (we added `storage/logs/slow_requests.log`) and consider adding a metrics exporter (Prometheus) or APM.

If you want, I can add a `docker-compose` + `nginx` + `php-fpm` example to run locally.
