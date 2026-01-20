FROM php:8.2-cli

# Instalar extensiones necesarias
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && apt-get clean

# Establecer directorio de trabajo
WORKDIR /app

# Copiar archivos
COPY index.php /app/

# Exponer puerto (Render usa la variable PORT)
EXPOSE 10000

# Comando para iniciar el servidor PHP
CMD php -S 0.0.0.0:${PORT:-10000} -t /app
