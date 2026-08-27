# Production Deployment Guide for Hostinger VPS

Complete, step-by-step guide to deploy the **Marketplace & Job Live Data Extractor Stack** (CodeIgniter 4 Gateway + Python Extraction Microservice + Redis Cache) on a **Hostinger VPS** (Ubuntu 22.04 / 24.04 LTS).

---

## 1. Stack Overview on Hostinger VPS

```mermaid
graph TD
    Client["Client / Web Browser"] -->|HTTPS :443| Nginx["Nginx Reverse Proxy & SSL"]
    Nginx -->|/api & / (Web Dashboard)| CI4["CodeIgniter 4 Gateway (:8085 / PHP-FPM)"]
    CI4 -->|Cache Check / Storage| Redis["Redis Server (:6379)"]
    CI4 -->|DB Usage / Audit Logs| MySQL["MySQL / SQLite DB"]
    CI4 -->|Internal HTTP Proxy| PyService["Python Extractor Microservice (:8000)"]
    PyService -->|Anti-Bot Stealth Engine| Stealth["Playwright Chromium + TLS Impersonation"]
    Stealth -->|Scrape| Targets["OLX India / CarDekho / Naukri.com"]
```

---

## 2. Server Prerequisites & Package Installation

SSH into your Hostinger VPS as `root`:
```bash
ssh root@YOUR_SERVER_IP
```

Update system packages and install prerequisites:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git zip unzip ufw redis-server

# Install PHP 8.3 and required extensions
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-curl php8.3-mbstring \
    php8.3-intl php8.3-sqlite3 php8.3-mysql php8.3-xml php8.3-zip php8.3-redis

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Python 3 & Virtual Environment
sudo apt install -y python3 python3-pip python3-venv python3-dev build-essential

# Install Nginx
sudo apt install -y nginx
```

---

## 3. Clone / Upload Code to VPS

### Option A: Using Git (Recommended)
On your local machine, initialize Git, commit, and push to your private repository (e.g. GitHub/GitLab):
```bash
# On Local Machine (e:\scraper):
git init
git add .
git commit -m "Initial commit - full multi-platform scraper stack"
git branch -M main
git remote add origin git@github.com:YOUR_USER/YOUR_REPO.git
git push -u origin main
```

Then on your Hostinger VPS:
```bash
cd /var/www
sudo git clone git@github.com:YOUR_USER/YOUR_REPO.git scraper
sudo chown -R www-data:www-data /var/www/scraper
cd /var/www/scraper
```

### Option B: Using SCP / Rsync from Local Windows
From your local Windows PowerShell:
```powershell
# In e:\scraper:
tar --exclude="extractor/.venv" --exclude="vendor" --exclude="writable/*.sqlite" --exclude=".git" -czvf scraper_deploy.tar.gz .
scp scraper_deploy.tar.gz root@YOUR_SERVER_IP:/var/www/
ssh root@YOUR_SERVER_IP "cd /var/www && mkdir -p scraper && tar -xzvf scraper_deploy.tar.gz -C scraper && chown -R www-data:www-data /var/www/scraper && rm scraper_deploy.tar.gz"
```

---

## 4. Backend Dependencies & Environment Configuration

### Step A: PHP & CodeIgniter 4 Setup
```bash
cd /var/www/scraper

# Install PHP dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Create and configure .env
cp .env.example .env
php spark key:generate

# Ensure writable directories have proper permissions
sudo chmod -R 775 writable
sudo chown -R www-data:www-data writable
```

### Step B: Python Microservice Setup & Playwright Binaries
```bash
cd /var/www/scraper

# Create Python virtual environment
python3 -m venv extractor/.venv
source extractor/.venv/bin/activate

# Install Python dependencies
pip install --upgrade pip
pip install -r extractor/requirements.txt

# Install Playwright browser and system OS libraries
playwright install chromium
playwright install-deps chromium

# Exit virtualenv
deactivate
```

---

## 5. Configure System Services (Systemd)

Create background systemd services so both the Python extractor and CodeIgniter run automatically and recover on reboots.

### 1. Python Extractor Service (`/etc/systemd/system/scraper-python.service`)
```ini
[Unit]
Description=Marketplace Python Extractor Microservice
After=network.target redis-server.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/scraper
ExecStart=/var/www/scraper/extractor/.venv/bin/python -m uvicorn extractor.main:app --host 127.0.0.1 --port 8000 --workers 2
Restart=always
RestartSec=3
Environment=PYTHONUNBUFFERED=1

[Install]
WantedBy=multi-user.target
```

### 2. Enable and Start Services
```bash
# Reload systemd
sudo systemctl daemon-reload

# Start and enable Redis
sudo systemctl enable --now redis-server

# Start and enable Python Extractor
sudo systemctl enable --now scraper-python

# Check Python Extractor Status
sudo systemctl status scraper-python
```

---

## 6. Nginx Web Server & SSL Configuration

Configure Nginx to serve the CodeIgniter 4 application with PHP 8.3 FPM and reverse proxy support.

Create `/etc/nginx/sites-available/scraper`:
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com; # or YOUR_SERVER_IP
    root /var/www/scraper/public;
    index index.php index.html;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/scraper_access.log;
    error_log /var/log/nginx/scraper_error.log;
}
```

Enable site and restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/scraper /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Free SSL Certificate with Let's Encrypt Certbot
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

---

## 7. Firewall (UFW) Configuration
```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

---

## 8. Post-Deployment Verification

1. **Verify Health Endpoint**:
   ```bash
   curl https://your-domain.com/api/v1/health
   ```
2. **Verify Live Job Search**:
   ```bash
   curl -H "X-API-Key: dev-local-api-key" \
     "https://your-domain.com/api/v1/naukri/listings?city=Bangalore&experience=0&limit=5&fresh=1"
   ```
3. **Open the Web UI**:
   Visit `https://your-domain.com/` in your browser!
