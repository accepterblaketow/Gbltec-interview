FROM php:7.2.34-apache

# Composer 1.10.15
RUN curl -sS https://getcomposer.org/installer | php -- --version=1.10.15 --install-dir=/usr/local/bin --filename=composer

# Debian buster 已進 archive：改 sources、關閉 Valid-Until、安裝 supervisor、清理
RUN set -eux; \
  printf "deb http://archive.debian.org/debian buster main contrib non-free\n\
deb http://archive.debian.org/debian buster-updates main contrib non-free\n\
deb http://archive.debian.org/debian-security buster/updates main contrib non-free\n" > /etc/apt/sources.list; \
  echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until; \
  apt-get -o Acquire::Check-Valid-Until=false update; \
  apt-get install -y --no-install-recommends supervisor; \
  rm -rf /var/lib/apt/lists/*

# 啟用 mod_rewrite（簡單路由）
RUN a2enmod rewrite \
 && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 放入設定與程式
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY src/ /var/www/html/

# 修改 DocumentRoot → /public
RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's#<Directory /var/www/>#<Directory /var/www/html/public/>#g' /etc/apache2/apache2.conf
 
WORKDIR /var/www/html

CMD ["/usr/bin/supervisord", "-n"]
