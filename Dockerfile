FROM php:8.2-apache

# Enable commonly used extensions (safe to keep)
RUN docker-php-ext-install mysqli pdo pdo_mysql || true

# Enable Apache rewrite (optional but useful)
RUN a2enmod rewrite

# Copy project files into Apache web root
COPY . /var/www/html/

# Set correct permissions (basic)
RUN chown -R www-data:www-data /var/www/html

# Render uses $PORT, so make Apache listen on it
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/:80/:${PORT}/' /etc/apache2/sites-available/000-default.conf

EXPOSE 80
