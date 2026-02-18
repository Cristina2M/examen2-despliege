# examen2-despliege


🚀 GUÍA DE DESPLIEGUE: EC2 + EFS + GITHUB ACTIONS
🏗️ FASE 1: El Almacenamiento (AWS EFS)
Esta es la base. Sin el disco compartido, los datos no persisten.
Paso 1: Ir a EFS ➔ Create file system.
Paso 2: Configuración rápida:
Nombre: NFS-Persistente
VPC: Selecciona la de tu proyecto.
Disponibilidad: Regional (Importante).
Paso 3: Clic en Create.
📋 NOTA DE ORO: Copia el File System ID que aparece (ej. fs-0123abcd). Lo usarás en el paso 3.

🔐 FASE 2: El Escudo de Seguridad (Security Groups)
Configura quién habla con quién. Si esto falla, nada conecta.
🛡️ SG Instancia (EC2)
Protocolo
Puerto
Origen
Motivo
SSH
22
0.0.0.0/0
Para GitHub Actions
HTTP
80
0.0.0.0/0
Acceso Web
Custom
8080
0.0.0.0/0
Tu App Docker
NFS
2049
SG-del-EFS
Solo si es necesario

🛡️ SG Almacenamiento (EFS)
Regla de Entrada: Tipo NFS ➔ Puerto 2049 ➔ Origen: SG de la EC2.

⚡ FASE 3: Lanzamiento con Auto-Configuración
Crea la EC2 y olvídate. Ella se preparará sola con este script.
En el apartado Advanced Details ➔ User Data, pega esto (sustituyendo tu ID):
Bash
#!/bin/bash
# 🛠️ AUTO-INSTALACIÓN AL ARRANQUE
dnf update -y
dnf install -y docker amazon-efs-utils
systemctl start docker && systemctl enable docker
usermod -aG docker ec2-user

# 🐳 INSTALAR DOCKER COMPOSE
curl -L https://github.com/docker/compose/releases/download/v2.27.0/docker-compose-linux-x86_64 -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose

# 💾 MONTAR DISCO EFS (Sustituye fs-XXXXX)
mkdir -p /efs
mount -t efs fs-XXXXXXXX:/ /efs
echo "fs-XXXXXXXX:/ /efs efs _netdev,tls 0 0" >> /etc/fstab

# 📂 PERMISOS FINALES
mkdir -p /home/ec2-user/app
chown -R ec2-user:ec2-user /home/ec2-user /efs



🔑 FASE 4: Llaves Maestras (GitHub Secrets)
No escribas contraseñas en el código. Úsalas como secretos.
Ve a Settings ➔ Secrets ➔ Actions y crea estos tres:
EC2_HOST ➔ 🌐 IP Pública de tu instancia.
EC2_USER ➔ 👤 ec2-user o ubuntu.
EC2_KEY ➔ 🔑 Contenido de tu archivo .pem.





🤖 FASE 5: El Cerebro (GitHub Actions Workflow)
Crea el archivo .github/workflows/deploy.yml para automatizar todo.
YAML
name: Deploy PHP App to EC2

on:
  push:
    branches:
      - main

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Descargar repositorio
        uses: actions/checkout@v4

      - name: 1. Copiar archivos a la EC2 (SCP)
        uses: appleboy/scp-action@v0.1.7
        with:
          host: ${{ secrets.EC2_HOST }}
          username: ${{ secrets.EC2_USER }}
          key: ${{ secrets.EC2_KEY }}
          source: "."
          target: "~/app"

      - name: 2. Ejecutar comandos vía SSH
        uses: appleboy/ssh-action@v1.0.0
        with:
          host: ${{ secrets.EC2_HOST }}
          username: ${{ secrets.EC2_USER }}
          key: ${{ secrets.EC2_KEY }}
          script: |
            set -e
            
            echo "🐳 Comprobando Docker (Ubuntu Mode)"
            # Si Docker no existe, actualizamos repositorios e instalamos
            if ! command -v docker &> /dev/null; then
              sudo apt-get update
              sudo apt-get install -y docker.io
              sudo systemctl start docker
              sudo systemctl enable docker
            fi

            echo "🧩 Comprobando Docker Compose"
            # Instalación manual de Docker Compose v2
            if ! command -v docker-compose &> /dev/null; then
              sudo curl -L "https://github.com/docker/compose/releases/download/v2.27.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
              sudo chmod +x /usr/local/bin/docker-compose
            fi

            echo "📂 Gestionando archivos y NFS"
            # Creamos la carpeta EFS si no existe (asegúrate de que tu disco esté montado aquí si usas AWS EFS real)
            sudo mkdir -p /efs/app
            
            # Copiamos el código nuevo. '|| true' evita errores si la carpeta está vacía al principio
            if [ -d "~/app/app" ]; then
                sudo cp -r ~/app/app/* /efs/app/
            else
                # Si tu código está en la raíz del repo
                sudo cp -r ~/app/* /efs/app/
            fi

            echo "🚀 Reiniciando contenedores"
            cd ~/app
            
            # Bajamos los contenedores antiguos (si existen)
            sudo docker-compose down || true
            
            # Levantamos los nuevos forzando la reconstrucción
            sudo docker-compose up --build -d



🏁 FINAL DEL EXAMEN
Si todo ha salido bien, tu aplicación debería estar brillando en:
👉 http://tu-ip-publica:8080
💡 Consejo Pro: Si la web no carga, revisa que el Security Group tenga abierto el puerto 8080 hacia 0.0.0.0/0.
(Para comprobar que la VPC está bien, hacer:
ssh -i "prep-examen.pem" ubuntu@<IP_EC2>)



# SECURIZAR

Crear carpeta dentro de web llamada certs
Dentro de certs
certificado.crt
clave.key

En certificado.crt: 
	abrimos los certificados con el bloc de notas y los pegamos en esta carpeta.
	peleamos primero el cert y luego los dos intermediate.
En clave.key:
	abrimos con el bloc de notas y pegamos la clave key


En web -> default.conf colocamos:



server {
    # 1. BLOQUE REDIRECCIÓN (Si entran por HTTP, los manda a HTTPS)
    listen 80;
    server_name localhost; 
    return 301 https://$host$request_uri;
}

server {
    # 2. BLOQUE SEGURO (HTTPS)
    listen 443 ssl;
    server_name localhost;

    root /var/www/html;
    index index.php index.html;

    # --- TUS CERTIFICADOS ---
    ssl_certificate /etc/nginx/certs/certificado.crt;
    ssl_certificate_key /etc/nginx/certs/clave.key;

    # Seguridad SSL básica
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # --- PROCESAMIENTO PHP ---
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        # ⚠️ AQUÍ ESTÁ LA CLAVE: Ponemos 'php' porque así se llama tu servicio en docker-compose
        fastcgi_pass php:9000; 
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}




Cambiamos el docker-compose de la raiz del proyecto

version: "3.9"

services:
  web:
    build: ./web
    ports:
      - "80:80"     # Puerto estándar HTTP (para la redirección)
      - "443:443"   # Puerto estándar HTTPS (Tu web segura)
    volumes:
      - ./app:/var/www/html:z
      # Montamos la configuración de Nginx
      - ./web/default.conf:/etc/nginx/conf.d/default.conf
      # Montamos la carpeta de certificados
      - ./web/certs:/etc/nginx/certs
    depends_on:
      - php
    user: root

  php:
    build: ./php
    volumes:
      - ./app:/var/www/html:z


commit para arriba, y prueba https://tupaginaweb.es


en caso de errores puedes mirar:
ssh -i "Prep-Examen-Key.pem" ubuntu@3.225.14.125

cd ~/app

ls -R web

sudo docker-compose logs web

