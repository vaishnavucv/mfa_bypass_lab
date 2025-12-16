#!/bin/bash

# FastLAN Employee Portal - Docker Quick Start Script

set -e

# Color codes
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "=========================================="
echo "FastLAN Employee Portal - Docker Setup"
echo "=========================================="
echo ""

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}✗${NC} Docker is not installed"
    echo "  Install with: sudo apt install docker.io -y"
    exit 1
fi
echo -e "${GREEN}✓${NC} Docker found: $(docker --version)"

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}✗${NC} Docker Compose is not installed"
    echo "  Install with: sudo apt install docker-compose -y"
    exit 1
fi
echo -e "${GREEN}✓${NC} Docker Compose found: $(docker-compose --version)"

echo ""
echo -e "${YELLOW}➜${NC} Starting containers..."
echo ""

# Stop any existing containers
docker-compose down 2>/dev/null || true

# Start the application
docker-compose up -d

echo ""
echo -e "${YELLOW}➜${NC} Waiting for services to be ready..."
sleep 5

# Check if containers are running
if [ "$(docker ps -q -f name=fastlan-webapp)" ] && [ "$(docker ps -q -f name=fastlan-mysql)" ]; then
    echo -e "${GREEN}✓${NC} All containers are running"
else
    echo -e "${RED}✗${NC} Some containers failed to start"
    echo "  Run 'docker-compose logs' to see errors"
    exit 1
fi

echo ""
echo "=========================================="
echo -e "${GREEN}✅ Docker Setup Complete!${NC}"
echo "=========================================="
echo ""
echo "🐳 Running Containers:"
docker-compose ps
echo ""
echo "🌐 Application URL:"
echo "  http://localhost:8080"
echo ""
echo "🔐 Default Login Credentials:"
echo "  Admin: admin@fastlan.com / Admin@123"
echo "  User:  john.doe@fastlan.com / User@123"
echo ""
echo "📊 Database Connection:"
echo "  Host: localhost:3306"
echo "  User: fastlan"
echo "  Pass: fastlan123"
echo "  DB:   employee_portal"
echo ""
echo "📝 Useful Commands:"
echo "  View logs:        docker-compose logs -f"
echo "  Stop app:         docker-compose down"
echo "  Restart:          docker-compose restart"
echo "  Rebuild:          docker-compose up -d --build"
echo ""
echo "=========================================="
