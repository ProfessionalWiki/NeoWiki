# NeoWiki Docker

Try NeoWiki locally or deploy it to a server.

## Prerequisites

- Docker and Docker Compose

## Local setup

1. Start the containers:
   ```bash
   docker compose up -d
   ```

2. Wait for all containers to be healthy:
   ```bash
   docker compose ps
   ```

3. Initialize the database and Neo4j:
   ```bash
   make install-db
   make load-neo4j-users
   ```

4. Load demo data:
   ```bash
   make import-demo-data
   ```

5. Open http://localhost:8484

To log in, use username `AdminName` and the password from `.env` (`AdminPassword` by default).

## Server deployment

To deploy on a server with automatic HTTPS via Caddy:

1. Edit `.env` and change all values marked with `# Change for production`, including
   `MW_SERVER` (e.g., `https://wiki.example.com`)

2. Start all services including Caddy:
   ```bash
   docker compose --profile server up -d
   ```

3. Follow steps 2-4 from the local setup above

4. Access your wiki at your configured `MW_SERVER` URL
