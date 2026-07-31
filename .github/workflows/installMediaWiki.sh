#! /bin/bash

MW_BRANCH=$1
EXTENSION_NAME=$2

# Cloned rather than fetched as a tarball: phpunit.xml.template is export-ignore'd
# in MediaWiki's .gitattributes, and `composer phpunit:config` needs it.
git clone --depth 1 --branch "$MW_BRANCH" https://github.com/wikimedia/mediawiki.git mediawiki

cd mediawiki

composer install
php maintenance/install.php --dbtype sqlite --dbuser root --dbname mw --dbpath $(pwd) --pass AdminPassword WikiName AdminUser


cat <<'EOT' >> LocalSettings.php
error_reporting(E_ALL| E_STRICT);
ini_set("display_errors", "1");
$wgShowExceptionDetails = true;
$wgShowDBErrorBacktrace = true;
$wgDevelopmentWarnings = true;

wfLoadExtension( "Scribunto" );

$wgNeoWikiNeo4jInternalWriteUrl = 'bolt://neo4j:password@localhost:7689';
$wgNeoWikiNeo4jInternalReadUrl = 'bolt://mediawiki_read:mediawiki_read@localhost:7689';
EOT

cat <<EOT >> LocalSettings.php
wfLoadExtension( "$EXTENSION_NAME" );
EOT

cat <<EOT >> composer.local.json
{
	"extra": {
		"merge-plugin": {
			"merge-dev": true,
			"include": [
				"extensions/$EXTENSION_NAME/composer.json"
			]
		}
	}
}
EOT

cd extensions
git clone -b $MW_BRANCH https://github.com/wikimedia/mediawiki-extensions-Scribunto.git Scribunto --depth 1
