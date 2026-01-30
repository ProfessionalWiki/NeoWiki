include .env

dockerCompose := docker compose

wiki-production-image:
	docker build . --file Dockerfile --pull --target final-mw -t ghcr.io/professionalwiki/neowiki:latest

install-db:
	${dockerCompose} exec -T mediawiki bash -c '/wait-for-it.sh db:3306 -t 60'
	${dockerCompose} exec -T mediawiki mv LocalSettings.php __LocalSettings.php
	${dockerCompose} exec -T mediawiki \
		php maintenance/install.php --dbuser ${MARIADB_USER} --dbpass ${MARIADB_PASSWORD} \
			--dbname ${MARIADB_DATABASE} --dbserver db:3306 --lang en \
			--pass ${MW_ADMIN_PASSWORD} \
			--server ${MW_SERVER} \
			SiteName AdminName
	${dockerCompose} exec -T mediawiki rm LocalSettings.php
	${dockerCompose} exec -T mediawiki mv __LocalSettings.php LocalSettings.php
	${dockerCompose} exec -T mediawiki php maintenance/run.php update --quick

load-neo4j-users:
	${dockerCompose} exec -T mediawiki bash -c '/wait-for-it.sh neo:7687 -t 60'
	${dockerCompose} exec -T neo bash -c \
		"echo \"CREATE USER ${NEO4J_USERNAME_READ} SET PASSWORD '${NEO4J_PASSWORD_READ}' CHANGE NOT REQUIRED; GRANT ROLE reader TO ${NEO4J_USERNAME_READ};\" | cypher-shell -u neo4j -p ${NEO4J_PASSWORD} -a bolt://localhost:7687"

test:
	bash test.sh

import-demo-data:
	${dockerCompose} exec -T mediawiki php extensions/NeoWiki/maintenance/ImportDemoData.php

update:
	${dockerCompose} pull
	${dockerCompose} up -d
	${dockerCompose} exec -T mediawiki php maintenance/run.php update --quick
