.PHONY: rebuild clean start stop restart

# Rebuild Docker containers with fresh cache and cleanup
rebuild:
	docker compose down
	docker compose build --no-cache
	docker image prune -f
	docker compose up -d

# Start containers normally
start:
	docker compose up -d

# Stop and remove containers
stop:
	docker compose down

# Restart without rebuilding
restart:
	docker compose down
	docker compose up -d

# Clean unused Docker images and containers
clean:
	docker system prune -a --volumes -f