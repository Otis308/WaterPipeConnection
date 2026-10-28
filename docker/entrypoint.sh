#!/bin/bash

# Cấp quyền cho toàn bộ thư mục để không bị lỗi ghi file (Session/Logs)
chmod -R 777 /var/www/html

# Chạy thẳng máy chủ Apache để giữ container hoạt động
exec apache2-foreground