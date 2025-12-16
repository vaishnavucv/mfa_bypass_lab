# FastLAN Employee Portal - Docker Deployment

## Quick Start (3 Steps)

### 1. Install Docker (if not already installed)
```bash
sudo apt update
sudo apt install docker.io docker-compose -y
sudo systemctl start docker
sudo usermod -aG docker $USER
```

### 2. Start the Application
```bash
bash docker-start.sh
```
**OR manually:**
```bash
docker-compose up -d
```

### 3. Access the Application
Open browser: **http://localhost:8080**

Login with:
- **Admin**: admin@fastlan.com / Admin@123
- **User**: john.doe@fastlan.com / User@123

## What Gets Deployed

### Services
1. **Web Application** (fastlan-webapp)
   - PHP 8.1 CLI
   - Built-in PHP server on port 8080
   - Auto-configured to connect to database

2. **MySQL Database** (fastlan-mysql)
   - MySQL 8.0
   - Auto-initialized with all schemas:
     - Base schema (users, projects, assignments)
     - MFA table
     - User approval system

### Features
- ✅ Zero configuration needed
- ✅ Database auto-initialized
- ✅ Persistent data storage
- ✅ Isolated network
- ✅ Health checks enabled
- ✅ Volume mounting for development

## File Structure

```
fastlan-mfa-webapp/
├── Dockerfile                 # PHP web app container definition
├── docker-compose.yml         # Multi-container orchestration
├── config.docker.php          # Docker-specific database config
├── .dockerignore             # Files to exclude from build
├── docker-start.sh           # Quick start script
├── DOCKER.md                 # Detailed Docker documentation
└── [application files...]
```

## Commands

### Start Application
```bash
docker-compose up -d
```

### Stop Application
```bash
docker-compose down
```

### View Logs
```bash
docker-compose logs -f web    # Web app logs
docker-compose logs -f db     # Database logs
```

### Restart Services
```bash
docker-compose restart
```

### Rebuild (after code changes to Dockerfile)
```bash
docker-compose up -d --build
```

### Complete Reset (removes all data)
```bash
docker-compose down -v
docker-compose up -d
```

## Port Mapping

| Service    | Container Port | Host Port |
|------------|---------------|-----------|
| Web App    | 8080          | 8080      |
| MySQL      | 3306          | 3306      |

## Environment Variables

All database credentials are auto-configured via environment variables:

```yaml
DB_HOST=db
DB_USER=fastlan
DB_PASS=fastlan123
DB_NAME=employee_portal
```

## Troubleshooting

### Containers won't start
```bash
docker-compose logs
```

### Database connection failed
```bash
# Check if MySQL is ready
docker exec -it fastlan-mysql mysqladmin ping -h localhost -u fastlan -pfastlan123
```

### Reset everything
```bash
docker-compose down -v
docker-compose up -d
```

### Access container shell
```bash
docker exec -it fastlan-webapp bash
docker exec -it fastlan-mysql bash
```

## Development Mode

Code changes are reflected immediately (no rebuild needed):
1. Edit PHP files
2. Refresh browser
3. Changes are live

Database schema changes require:
```bash
docker-compose down -v
docker-compose up -d
```

## Production Considerations

⚠️ **This setup is for DEVELOPMENT/TESTING only!**

For production, you would need:
- Strong passwords
- SSL/TLS encryption
- Reverse proxy (nginx/traefik)
- Proper secret management
- Restricted database access
- Security hardening

## Comparison: Docker vs Native

| Feature              | Docker              | Native (SETUP.sh)   |
|---------------------|---------------------|---------------------|
| Setup Time          | 2 minutes           | 5 minutes           |
| Dependencies        | Docker only         | PHP + MySQL         |
| Isolation           | ✅ Containerized    | ❌ System-wide      |
| Portability         | ✅ Works anywhere   | ❌ Ubuntu/Debian    |
| Data Persistence    | ✅ Volumes          | ✅ System DB        |
| Easy Cleanup        | ✅ One command      | ❌ Manual cleanup   |
| Development         | ✅ Hot reload       | ✅ Hot reload       |

## See Also

- **DOCKER.md** - Comprehensive Docker documentation
- **SETUP.sh** - Native installation script
- **QUICKSTART.md** - Native quick start guide
