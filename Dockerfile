FROM php:7.3-apache

RUN apt-get update \
    && apt-get install -y --force-yes --no-install-recommends libgraphicsmagick1-dev libpng-dev libjpeg-dev curl git unzip \
    && rm -rf /var/lib/apt/lists/*

RUN cd ~ && curl -sS https://getcomposer.org/installer -o composer-setup.php \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    

RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install gd

RUN pecl install gmagick-2.0.4RC1 \
    && docker-php-ext-enable gmagick \
    && rm -rf /tmp/pear

RUN { \
      echo '<Directory /var/www/html>'; \
      echo '  RewriteEngine on'; \
      echo '  RewriteCond %{REQUEST_FILENAME} !-f'; \
      echo '  RewriteRule .* /index.php [L,QSA]'; \
      echo '</Directory>'; \
    } >> /etc/apache2/conf-available/photon.conf

RUN { \
	echo 'ServerName localhost'; \
    } >> /etc/apache2/apache2.conf

RUN a2enmod rewrite
RUN a2enconf photon

COPY . /var/www/html

RUN cd /var/www/html/ && composer update --no-dev

RUN sed -i.bak -e 's/ *FILTER_FLAG_NO_PRIV_RANGE *|//g' /var/www/html/index.php