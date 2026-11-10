Database: micronet
User:     micronet_user
Password: micronet_password
Host:     postgres
Port:     5432

user name for account admin pwd 8218.Kelmo  http://localhost:8000/admin/

Deployment Instructions
Clone the repository

bash
git clone https://github.com/yourusername/micronet-saas.git
cd micronet-saas
Create environment file

bash
cp .env.example .env
# Edit .env with your configuration
Build and run with Docker

bash
docker-compose up -d
Run migrations

bash
docker-compose exec backend python manage.py migrate
Create superuser

bash
docker-compose exec backend python manage.py createsuperuser
Access the application

Frontend: http://localhost

API: http://localhost/api/

Admin: http://localhost/admin/

Security Considerations
HTTPS: Use Let's Encrypt with nginx for production

Environment variables: Never commit .env file

Payment webhook: Verify signatures with shared secret

Tally connection: Use secure tunnel (ngrok/Cloudflare Tunnel)

Rate limiting: Implement for API endpoints

CORS: Restrict to your domain only

SQL injection: Django ORM protects against this

XSS: React automatically escapes content

This architecture provides a complete SaaS solution with:

Multi-tenant isolation

Secure payment integration

Tally/n8n integration

Invoice processing workflow

Admin dashboard

Production-ready configuration











PS C:\n8n\projects\micronet-saas> # Check container status
PS C:\n8n\projects\micronet-saas> docker-compose ps
time="2026-08-27T01:01:58-07:00" level=warning msg="C:\\n8n\\projects\\micronet-saas\\docker-compose.yml: the attribute `version` is obsolete, it will be ignored, please remove it to avoid potential confusion"
NAME                       IMAGE            COMMAND                  SERVICE    CREATED              STATUS              PORTS
micronet-saas-postgres-1   postgres:15      "docker-entrypoint.s…"   postgres   About a minute ago   Up About a minute   0.0.0.0:5432->5432/tcp, [::]:5432->5432/tcp
micronet-saas-redis-1      redis:7-alpine   "docker-entrypoint.s…"   redis      About a minute ago   Up About a minute   0.0.0.0:6379->6379/tcp, [::]:6379->6379/tcp
PS C:\n8n\projects\micronet-saas>
PS C:\n8n\projects\micronet-saas> # View backend logs to see why it crashed
PS C:\n8n\projects\micronet-saas> docker-compose logs backend
time="2026-08-27T01:02:00-07:00" level=warning msg="C:\\n8n\\projects\\micronet-saas\\docker-compose.yml: the attribute `version` is obsolete, it will be ignored, please remove it to avoid potential confusion"
backend-1  | sh: 4: gunicorn: not found
PS C:\n8n\projects\micronet-saas>









rebuuild back ed container
# Stop the backend
docker-compose stop backend

# Remove the existing backend container
docker-compose rm backend

# Rebuild with the new requirements
docker-compose build --no-cache backend

# Start everything again
docker-compose up -d






verify bACKEND WORKS
# Check container status
docker-compose ps

# Check backend logs
docker-compose logs backend --tail=20

# If backend is running, run migrations
docker-compose exec backend python manage.py migrate

# Create superuser
docker-compose exec backend python manage.py createsuperuser