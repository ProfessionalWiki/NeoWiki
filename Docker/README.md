# NeoWiki Docker Deployment

Deploy NeoWiki on a VPS with automatic HTTPS via Caddy.

## Prerequisites

- Docker and Docker Compose installed on your VPS
- A domain name pointing to your VPS
- Ports 80 and 443 open

## Setup

1. Copy this directory to your VPS

2. Edit `.env` and change all values marked with `# Change for production`:
   - `MW_SERVER` - your wiki's URL (e.g., `https://wiki.example.com`)
   - `MARIADB_ROOT_PASSWORD` - database root password
   - `MARIADB_PASSWORD` - database user password
   - `MW_ADMIN_PASSWORD` - MediaWiki admin password
   - `NEO4J_PASSWORD` - Neo4j password
   - `NEO4J_PASSWORD_READ` - Neo4j read-only user password

3. Start the containers:
   ```bash
   docker compose up -d
   ```

4. Wait for containers to be healthy, then initialize the database:
   ```bash
   make install-db
   make load-neo4j-users
   ```

5. Optionally load NeoWiki demo data:
   ```bash
   make import-demo-data
   ```

6. Access your wiki at your configured `MW_SERVER` URL

## Extra

Server hardening is not covered here.
