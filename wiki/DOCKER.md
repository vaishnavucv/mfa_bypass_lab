# FastLAN Employee Portal - Docker Setup Guide

This guide explains how to run the FastLAN Employee Portal using Docker and Docker Compose.

## Prerequisites

- Docker Engine 20.10+
- Docker Compose 2.0+

### Install Docker (Ubuntu)

```bash
# Update package index
sudo apt update

# Install Docker
sudo apt install docker.io docker-compose -y

# Start and enable Docker
sudo systemctl start docker
sudo systemctl enable docker

# Add your user to docker group (optional, to run without sudo)
sudo usermod -aG docker $USER
newgrp docker

# Verify installation
docker --version
docker-compose --version
```

## Quick Start with Docker

### Option 1: Using Docker Compose (Recommended)

Simply run:

```bash
docker-compose up -d
```

This will:
- Build the PHP web application container
- Pull and start MySQL 8.0 container
- Create the database and import all schemas automatically
- Start the web server on http://localhost:8080

### Option 2: Build and Run Manually

```bash
# Build the web application image
docker build -t fastlan-webapp .

# Create a network
docker network create fastlan-network

# Run MySQL container
docker run -d \
  --name fastlan-mysql \
  --network fastlan-network \
  -e MYSQL_ROOT_PASSWORD=rootpassword \
  -e MYSQL_DATABASE=employee_portal \
  -e MYSQL_USER=fastlan \
  -e MYSQL_PASSWORD=fastlan123 \
  -p 3306:3306 \
  mysql:8.0

# Wait for MySQL to be ready
sleep 10

# Run web application container
docker run -d \
  --name fastlan-webapp \
  --network fastlan-network \
  -p 8080:8080 \
  -e DB_HOST=fastlan-mysql \
  -e DB_USER=fastlan \
  -e DB_PASS=fastlan123 \
  -e DB_NAME=employee_portal \
  fastlan-webapp
```

## Accessing the Application

Once containers are running, open your browser to:

**http://localhost:8080**

### Default Login Credentials

#### Admin Account
- Email: `admin@fastlan.com`
- Password: `Admin@123`

#### Test User Accounts
- Email: `john.doe@fastlan.com` / Password: `User@123`
- Email: `jane.smith@fastlan.com` / Password: `User@123`

## Docker Compose Commands

### Start the application
```bash
docker-compose up -d
```

### Stop the application
```bash
docker-compose down
```

### Stop and remove all data (including database)
```bash
docker-compose down -v
```

### View logs
```bash
# All services
docker-compose logs -f

# Web application only
docker-compose logs -f web

# Database only
docker-compose logs -f db
```

### Restart services
```bash
docker-compose restart
```

### Rebuild containers (after code changes)
```bash
docker-compose up -d --build
```

### Check running containers
```bash
docker-compose ps
```

## Architecture

```
┌─────────────────────────────────────┐
│   Browser (localhost:8080)          │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│   fastlan-webapp Container          │
│   - PHP 8.1 CLI                     │
│   - Built-in Server (0.0.0.0:8080)  │
│   - Application Code                │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│   fastlan-mysql Container           │
│   - MySQL 8.0                       │
│   - Database: employee_portal       │
│   - User: fastlan/fastlan123        │
└─────────────────────────────────────┘
```

## Configuration

### Environment Variables

The following environment variables can be customized in `docker-compose.yml`:

#### Database Service (db)
- `MYSQL_ROOT_PASSWORD` - MySQL root password (default: rootpassword)
- `MYSQL_DATABASE` - Database name (default: employee_portal)
- `MYSQL_USER` - MySQL user (default: fastlan)
- `MYSQL_PASSWORD` - MySQL password (default: fastlan123)

#### Web Application Service (web)
- `DB_HOST` - Database hostname (default: db)
- `DB_USER` - Database user (default: fastlan)
- `DB_PASS` - Database password (default: fastlan123)
- `DB_NAME` - Database name (default: employee_portal)

### Changing the Port

Edit `docker-compose.yml` to change the exposed port:

```yaml
web:
  ports:
    - "3000:8080"  # Change 3000 to your desired port
```

Then restart:
```bash
docker-compose down
docker-compose up -d
```

## Persistent Data

Database data is stored in a Docker volume named `mysql_data`. This ensures your data persists across container restarts.

To completely remove all data:
```bash
docker-compose down -v
```

## Troubleshooting

### Check if containers are running
```bash
docker-compose ps
```

### View web application logs
```bash
docker-compose logs web
```

### View database logs
```bash
docker-compose logs db
```

### Access web container shell
```bash
docker exec -it fastlan-webapp bash
```

### Access database container shell
```bash
docker exec -it fastlan-mysql mysql -u fastlan -pfastlan123 employee_portal
```

### Test database connection from web container
```bash
docker exec -it fastlan-webapp php -r "\$c = new mysqli('db', 'fastlan', 'fastlan123', 'employee_portal'); echo \$c->ping() ? 'Connected!' : 'Failed!';"
```

### Container won't start
```bash
# Check logs for errors
docker-compose logs

# Remove containers and try again
docker-compose down
docker-compose up -d
```

### Port already in use
```bash
# Check what's using port 8080
sudo lsof -i :8080

# Kill the process or change the port in docker-compose.yml
```

### Database initialization failed
```bash
# Remove everything and start fresh
docker-compose down -v
docker-compose up -d
```

## Development Workflow

### Making Code Changes

Since the application code is mounted as a volume, changes to PHP files are reflected immediately:

1. Edit any PHP file
2. Refresh your browser
3. Changes are live (no rebuild needed)

### Database Schema Changes

If you modify SQL files, you need to recreate the database:

```bash
# Stop and remove containers with volumes
docker-compose down -v

# Start fresh (will re-import SQL files)
docker-compose up -d
```

### Rebuilding the Image

If you modify the Dockerfile or add new dependencies:

```bash
docker-compose up -d --build
```

## Security Notes

This Docker setup is configured for **development and testing** purposes:

- Simple passwords are used
- Database port is exposed (3306)
- Logs directory has wide permissions (777)
- No SSL/TLS configuration

**Do NOT use this configuration in production without proper hardening!**

## Cleanup

### Remove all containers and networks
```bash
docker-compose down
```

### Remove containers, networks, and volumes
```bash
docker-compose down -v
```

### Remove images
```bash
docker rmi fastlan-webapp
docker rmi mysql:8.0
```

### Full cleanup
```bash
docker-compose down -v --rmi all
```

## Support

For issues or questions:
1. Check container logs: `docker-compose logs`
2. Verify containers are running: `docker-compose ps`
3. Check application logs: `docker exec fastlan-webapp cat logs/activity.log`
