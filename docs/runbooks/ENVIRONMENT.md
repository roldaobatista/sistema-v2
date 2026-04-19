# Environment — VPS

Instalado:
- Node 20.20.2, pnpm 10.33
- Docker 29.4.0 + Compose v5.1.3
- PHP 8.4.20, Composer 2.9.7
- gh CLI (autenticado como roldaosolution-spec)

Agentes têm `sudo NOPASSWD` — podem instalar qualquer coisa via apt/npm/docker.
Grupo `docker` já configurado pro usuário paperclip.
Porta 3100: Paperclip (ocupada). Use 8080+ pra preview de apps.
