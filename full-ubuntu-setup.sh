#!/usr/bin/env bash
set -Eeuo pipefail

#######################################
# Load environment variables from .env
#######################################
if [ -f ".env" ]; then
  set -a
  source .env
  set +a
else
  echo "ERROR: .env file not found!"
  exit 1
fi

#######################################
# Check required variables
#######################################
if [ -z "${DB_DATABASE:-}" ] || [ -z "${DB_USERNAME:-}" ] || [ -z "${DB_PASSWORD:-}" ]; then
  echo "ERROR: Environment variables DB_DATABASE, DB_USERNAME, and DB_PASSWORD must be set."
  exit 1
fi

#######################################
# Update package list
#######################################
echo "Updating package list..."
sudo apt-get update -y

#######################################
# Install MySQL Server
#######################################
echo "Installing MySQL Server..."
sudo apt-get install -y mysql-server

#######################################
# Start MySQL Service
#######################################
echo "Starting MySQL service..."
sudo systemctl start mysql

#######################################
# Enable MySQL on boot
#######################################
echo "Enabling MySQL to start on boot..."
sudo systemctl enable mysql

#######################################
# Secure MySQL
#######################################
echo "Running MySQL secure installation..."
sudo mysql_secure_installation

#######################################
# Create database and user
#######################################
echo "Creating database and user..."

sudo mysql <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\`;
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';
FLUSH PRIVILEGES;
EOF

#######################################
# Check MySQL status
#######################################
echo "Checking MySQL status..."
sudo systemctl status mysql --no-pager

echo "MySQL installation complete and database/user setup finished!"

#!/usr/bin/env bash
set -Eeuo pipefail

# Load environment variables from .env
if [ -f ".env" ]; then
  set -a
  source .env
  set +a
else
  echo "ERROR: .env file not found!"
  exit 1
fi

# Validate required variable
if [ -z "${APP_URL:-}" ]; then
  echo "ERROR: Environment variable APP_URL must be set."
  exit 1
fi

# Extract domain from APP_URL
DOMAIN=$(echo "$APP_URL" | sed -E 's~https?://~~g' | sed 's/\/.*//')

# Update package list
echo "Updating package list..."
sudo apt-get update -y

# Install Nginx
echo "Installing Nginx..."
sudo apt-get install -y nginx

# Install Certbot
echo "Installing Certbot and Nginx plugin..."
sudo apt-get install -y certbot python3-certbot-nginx

# Start and enable Nginx
echo "Starting Nginx..."
sudo systemctl start nginx
sudo systemctl enable nginx
  
# Configure Firewall
if command -v ufw >/dev/null 2>&1; then
  echo "Allowing Nginx through firewall..."
  sudo ufw allow 'Nginx Full'
fi

# Obtain SSL certificate
echo "Obtaining SSL certificate for $DOMAIN..."

sudo certbot --nginx \
  -d "$DOMAIN" \
  --non-interactive \
  --agree-tos \
  --email admin@"$DOMAIN" \
  --redirect

# Backup default config
sudo cp /etc/nginx/sites-available/default /etc/nginx/sites-available/default.bak

# Create Nginx configuration
echo "Configuring Nginx..."

sudo tee /etc/nginx/sites-available/default > /dev/null <<EOL
server {
    listen 80;
    server_name $DOMAIN;

    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl;
    server_name $DOMAIN;

    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";

    root /var/www/html;
    index index.html index.htm;

    location / {
        try_files \$uri \$uri/ =404;
    }
}
EOL

# Test Nginx configuration
echo "Testing Nginx config..."
sudo nginx -t

# Reload Nginx
echo "Reloading Nginx..."
sudo systemctl reload nginx

# Setup SSL auto-renewal
echo "Setting up auto-renewal..."

echo "0 3 * * * root certbot renew --quiet --post-hook 'systemctl reload nginx'" \
| sudo tee /etc/cron.d/certbot-renew > /dev/null

# Show Nginx status
sudo systemctl status nginx --no-pager

echo "------------------------------------"
echo "Nginx + SSL setup complete!"
echo "Visit: https://$DOMAIN"
echo "------------------------------------"

#!/usr/bin/env bash
set -Eeuo pipefail

############################################
# Load environment variables
############################################

if [[ -f ".env" ]]; then
    set -a
    source .env
    set +a
else
    echo ".env file not found"
    exit 1
fi

############################################
# Root check
############################################

if [[ $EUID -ne 0 ]]; then
    echo "Run as root"
    exit 1
fi

############################################
# Required variables
############################################

required_vars=(
DEPLOY_USER
APP_DIR
DOMAIN_NAME
CERTBOT_EMAIL
APP_NAME
APP_ENV
APP_URL
DB_CONNECTION
)

for v in "${required_vars[@]}"; do
  if [[ -z "${!v:-}" ]]; then
    echo "Missing environment variable: $v"
    exit 1
  fi
done

############################################
# Defaults
############################################

PHP_VERSION="8.3"
WEB_GROUP="www-data"
BASE_DIR="/var/www"
APP_PATH="$BASE_DIR/$APP_DIR"

############################################
# System update
############################################

apt update -y
apt upgrade -y

############################################
# Install packages
############################################

apt install -y \
nginx \
git \
curl \
unzip \
ufw \
certbot \
python3-certbot-nginx \
mysql-server \
php${PHP_VERSION} \
php${PHP_VERSION}-fpm \
php${PHP_VERSION}-mysql \
php${PHP_VERSION}-cli \
php${PHP_VERSION}-curl \
php${PHP_VERSION}-mbstring \
php${PHP_VERSION}-xml \
php${PHP_VERSION}-zip \
php${PHP_VERSION}-bcmath \
php${PHP_VERSION}-intl

############################################
# Install Composer
############################################

if ! command -v composer &> /dev/null
then
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
fi

############################################
# Enable services
############################################

systemctl enable nginx
systemctl enable php${PHP_VERSION}-fpm
systemctl enable mysql

systemctl start nginx
systemctl start php${PHP_VERSION}-fpm
systemctl start mysql

############################################
# Create deploy user
############################################

if ! id "$DEPLOY_USER" &>/dev/null; then
adduser --disabled-password --gecos "" "$DEPLOY_USER"
fi

############################################
# Check Laravel project
############################################

if [[ ! -d "$APP_PATH" ]]; then
echo "Laravel project not found: $APP_PATH"
exit 1
fi

chown -R "$DEPLOY_USER:$WEB_GROUP" "$APP_PATH"

############################################
# Install Laravel dependencies
############################################

cd "$APP_PATH"

sudo -u "$DEPLOY_USER" composer install \
--no-dev \
--optimize-autoloader \
--no-interaction

############################################
# Setup Laravel env
############################################

if [[ ! -f .env ]]; then
cp .env.example .env
fi

set_env () {
key=$1
value=$2

if grep -q "^$key=" .env; then
sed -i "s|^$key=.*|$key=\"$value\"|" .env
else
echo "$key=\"$value\"" >> .env
fi
}

set_env APP_NAME "$APP_NAME"
set_env APP_ENV "$APP_ENV"
set_env APP_DEBUG false
set_env APP_URL "$APP_URL"

############################################
# MySQL database
############################################

if [[ "$DB_CONNECTION" == "mysql" ]]; then

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\`;
CREATE USER IF NOT EXISTS '$DB_USERNAME'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'localhost';
FLUSH PRIVILEGES;
SQL

set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_DATABASE"
set_env DB_USERNAME "$DB_USERNAME"
set_env DB_PASSWORD "$DB_PASSWORD"

fi

############################################
# Laravel setup
############################################

sudo -u "$DEPLOY_USER" php artisan key:generate --force
sudo -u "$DEPLOY_USER" php artisan migrate --force

sudo -u "$DEPLOY_USER" php artisan config:cache
sudo -u "$DEPLOY_USER" php artisan route:cache || true
sudo -u "$DEPLOY_USER" php artisan view:cache || true

############################################
# Permissions
############################################

chown -R "$DEPLOY_USER:$WEB_GROUP" "$APP_PATH"

chmod -R 775 "$APP_PATH/storage"
chmod -R 775 "$APP_PATH/bootstrap/cache"

############################################
# Nginx configuration
############################################

PHP_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"

cat > /etc/nginx/sites-available/$APP_DIR <<EOF
server {
listen 80;
server_name $DOMAIN_NAME;

root $APP_PATH/public;
index index.php;

location / {
try_files \$uri \$uri/ /index.php?\$query_string;
}

location ~ \.php$ {
include snippets/fastcgi-php.conf;
fastcgi_pass unix:$PHP_SOCK;
}

location ~ /\. {
deny all;
}
}
EOF

ln -sf /etc/nginx/sites-available/$APP_DIR /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl reload nginx

############################################
# Firewall
############################################

ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

############################################
# SSL
############################################

certbot --nginx \
--non-interactive \
--agree-tos \
-m "$CERTBOT_EMAIL" \
-d "$DOMAIN_NAME" \
--redirect

############################################
# Done
############################################

echo "--------------------------------"
echo "Deployment Complete"
echo "Site: https://$DOMAIN_NAME"
echo "Path: $APP_PATH"
echo "--------------------------------"

