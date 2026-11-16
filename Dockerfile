# Estágio 1: Build dos assets front-end com Vite
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci || npm install
COPY . .
RUN npm run build

# Estágio 2: Runtime PHP do Laravel
FROM php:8.2-cli-alpine

# Instala extensões essenciais do PHP para Laravel (SQLite, PostgreSQL, MySQL, GD, etc.)
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite-dev \
    postgresql-dev \
    oniguruma-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql pdo_pgsql mbstring pcntl bcmath gd

# Copia Composer oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copia código do projeto
COPY . .

# Copia os assets construídos no estágio Node
COPY --from=assets /app/public/build ./public/build

# Instala dependências de produção do PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Ajusta permissões de escrita para cache e storage
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Escuta no host 0.0.0.0 e na porta dinâmica fornecida pelo Render ($PORT)
EXPOSE 8000
CMD ["sh", "-c", "php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
