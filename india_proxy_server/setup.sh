#!/bin/bash
set -e

echo "=== Setting up XYZFinders India Proxy on AWS Lightsail ==="

# 1. Update and install python & pip
sudo apt-get update
sudo apt-get install -y python3 python3-pip python3-venv

# 2. Create app directory if not exists
mkdir -p /home/ubuntu/india_proxy
cd /home/ubuntu/india_proxy

# 3. Create virtual environment
if [ ! -d "venv" ]; then
    python3 -m venv venv
fi

# 4. Install dependencies
./venv/bin/pip install --upgrade pip
./venv/bin/pip install fastapi uvicorn[standard] curl_cffi pydantic

# 5. Create systemd background service
sudo tee /etc/systemd/system/india-proxy.service > /dev/null << 'EOF'
[Unit]
Description=XYZFinders India Proxy Node
After=network.target

[Service]
User=ubuntu
WorkingDirectory=/home/ubuntu/india_proxy
ExecStart=/home/ubuntu/india_proxy/venv/bin/uvicorn app:app --host 0.0.0.0 --port 8080 --workers 2
Restart=always
RestartSec=3
Environment=PYTHONUNBUFFERED=1
Environment=PROXY_SECRET_TOKEN=xyz-india-secret-key-2026

[Install]
WantedBy=multi-user.target
EOF

# 6. Enable and start the service
sudo systemctl daemon-reload
sudo systemctl enable --now india-proxy
sudo systemctl restart india-proxy

echo "=== Setup Complete! Status: ==="
sudo systemctl status india-proxy --no-pager
