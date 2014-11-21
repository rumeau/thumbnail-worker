## Paperlast Image Thumbnail Worker

### Instalación

  1. Desplegar utilizando Elastic Beanstalk
  2. Configurar variables de entorno
    1. **AWS_ACCESS_KEY_ID**
    2. **AWS_SECRET_ACCESS_KEY**
    3. **AWS_DEFAULT_REGION**
  3. Establecer el endpoint a http://<ruta>/thumbnail
  4. Corregir permisos de escritura en directorios storage/*
  5. Instalar dependencias con composer
    1. `composer.phar install