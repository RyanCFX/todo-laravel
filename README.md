# Todo Laravel API

API REST desarrollada con Laravel para gestión de tareas, con soporte para autenticación JWT, backups, importación/exportación XML y notificaciones.

## Requisitos Previos

- Docker y Docker Compose
- Git
- Cuenta en un servicio de base de datos PostgreSQL (por ejemplo, Neon.tech)
- Cuenta en un servicio de correo SMTP (por ejemplo, Brevo/Sendinblue)

## Tecnologías Utilizadas

- PHP 8.1
- Laravel 10
- PostgreSQL
- Redis
- Nginx
- Docker
- JWT Authentication
- Swagger/OpenAPI para documentación

## Configuración del Proyecto

### 1. Clonar el Repositorio

```bash
git clone <url-del-repositorio>
cd todo-laravel
```

### 2. Configurar Variables de Entorno

Copia el archivo .env.sample (ajusta los valores según tu entorno):

```bash
cp .env.sample .env
```

### 3. Construir y Levantar los Contenedores

```bash
# Construir y levantar los contenedores
docker-compose up -d --build

```

### 4. Configuración Inicial del Proyecto

```bash
# Generar clave de la aplicación
docker-compose exec app php artisan key:generate

# Generar clave JWT
docker-compose exec app php artisan jwt:secret

# Generar documentación Swagger
docker-compose exec app php artisan l5-swagger:generate
```

## Estructura de Contenedores

El proyecto utiliza varios contenedores Docker:

- **app**: Contenedor principal de PHP/Laravel
- **nginx**: Servidor web que actúa como proxy inverso
- **redis**: Cache y gestión de sesiones

## Endpoints Principales

La documentación completa de la API está disponible en `/api/documentation` una vez que el proyecto esté en ejecución.

## Mantenimiento

### Logs
Los logs se encuentran en:
```
storage/logs/laravel.log
```

### Caché
Para limpiar la caché:
```bash
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
```

### Actualización
Para actualizar el proyecto:
```bash
git pull
docker-compose down
docker-compose build
docker-compose up -d
docker-compose exec app composer install
```

## Solución de Problemas Comunes

### Error de Permisos
Si encuentras errores de permisos en storage o bootstrap/cache:
```bash
docker-compose exec app chmod -R 777 storage bootstrap/cache
```

### Error de Conexión con Redis
Verifica que la configuración de Redis en `.env` apunte al contenedor:
```env
REDIS_HOST=redis
```

### Error de Conexión con PostgreSQL
- Verifica que las credenciales en `.env` sean correctas
- Asegúrate de que la IP desde donde te conectas esté permitida en tu servicio de base de datos

## Cron Jobs y Tareas Programadas

El proyecto incluye las siguientes tareas programadas:

### Notificaciones
- **Comando**: `notifications:process`
- **Frecuencia**: Cada minuto
- **Descripción**: Procesa y envía notificaciones pendientes (email y push) para las tareas

Para ver los cron jobs configurados:
```bash
# Ver la configuración de los cron jobs
docker-compose exec app php artisan schedule:list

# Ver los cron jobs en ejecución
docker-compose exec app php artisan schedule:work
```

Para probar las notificaciones manualmente:
```bash
# Probar envío de email
docker-compose exec app php artisan test:email test@test.com
```
