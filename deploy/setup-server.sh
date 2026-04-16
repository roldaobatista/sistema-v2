#!/bin/bash
# =============================================================================
# Kalibrium ERP - Setup Servidor de Produção (Hetzner/VPS)
# =============================================================================
# Instala: Docker, Git, UFW, fail2ban, unattended-upgrades, swap, logrotate
# Uso: curl -sSL <url> | bash   OU   bash setup-server.sh
# =============================================================================
set -euo pipefail

GREEN='\033[0;32m'
NC='\033[0m'
step() { echo -e "\n${GREEN}[$1]${NC} $2"; }

step "1/7" "Updating packages..."
apt-get update -qq
apt-get upgrade -y -qq

step "2/7" "Installing Docker..."
if ! command -v docker &>/dev/null; then
    curl -fsSL https://get.docker.com | sh
    systemctl enable docker
    systemctl start docker
else
    echo "Docker already installed, skipping."
fi

step "3/7" "Installing Git, fail2ban, unattended-upgrades..."
apt-get install -y -qq git fail2ban unattended-upgrades

# fail2ban: protect SSH against brute force
if [ ! -f /etc/fail2ban/jail.local ]; then
    cat > /etc/fail2ban/jail.local <<'JAIL'
[sshd]
enabled = true
port = ssh
filter = sshd
maxretry = 5
bantime = 3600
findtime = 600
JAIL
    systemctl enable fail2ban
    systemctl restart fail2ban
fi

# unattended-upgrades: auto-install security patches
if [ -f /etc/apt/apt.conf.d/20auto-upgrades ]; then
    echo "unattended-upgrades already configured."
else
    cat > /etc/apt/apt.conf.d/20auto-upgrades <<'AUTOUPG'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
AUTOUPG
fi

step "4/7" "Configuring UFW firewall..."
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

step "5/7" "Configuring swap (2GB)..."
if [ ! -f /swapfile ]; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
    # Optimize swap usage for production
    sysctl vm.swappiness=10
    echo 'vm.swappiness=10' >> /etc/sysctl.conf
    echo "Swap configured: 2GB"
else
    echo "Swap already exists, skipping."
fi

step "6/7" "Configuring Docker log rotation..."
mkdir -p /etc/docker
if [ ! -f /etc/docker/daemon.json ]; then
    cat > /etc/docker/daemon.json <<'DOCKERCFG'
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}
DOCKERCFG
    systemctl restart docker
else
    echo "Docker daemon.json already exists, skipping."
fi

step "7/7" "SSH hardening..."
# Disable root password login (key-only), keep PermitRootLogin yes for key auth
if grep -q "^PasswordAuthentication yes" /etc/ssh/sshd_config 2>/dev/null; then
    sed -i 's/^PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
    systemctl restart sshd
    echo "SSH password authentication disabled (key-only)."
else
    echo "SSH already configured for key-only auth."
fi

echo ""
echo -e "${GREEN}Setup complete!${NC}"
echo "  Docker, Git, UFW, fail2ban, swap, log rotation, SSH hardening — all ready."
echo "  Next: run deploy/setup-git-server.sh to configure Git."
