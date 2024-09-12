#! /bin/bash

MW_BRANCH=$1
EXTENSION_NAME=$2

wget https://github.com/wikimedia/mediawiki/archive/$MW_BRANCH.tar.gz -nv

tar -zxf $MW_BRANCH.tar.gz
mv mediawiki-$MW_BRANCH mediawiki

cd mediawiki

composer install
php maintenance/install.php --dbtype sqlite --dbuser root --dbname mw --dbpath $(pwd) --pass AdminPassword WikiName AdminUser


cat <<'EOT' >> LocalSettings.php
error_reporting(E_ALL| E_STRICT);
ini_set("display_errors", "1");
$wgShowExceptionDetails = true;
$wgShowDBErrorBacktrace = true;
$wgDevelopmentWarnings = true;

// wfLoadExtension( "Scribunto" );

$wgNeoWikiNeo4jInternalWriteUrl = 'bolt://neo4j:password@localhost:7689';
$wgNeoWikiNeo4jInternalReadUrl = 'bolt://mediawiki_read:mediawiki_read@localhost:7689';
$wgNeoWikiNeo4jExternalReadUrl = 'bolt://localhost:7687';
$wgNeoWikiNeo4jExternalReadUserName = 'mediawiki_read';
$wgNeoWikiNeo4jExternalReadPassword = 'mediawiki_read';
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

#cd extensions
#git clone -b $MW_BRANCH https://gerrit.wikimedia.org/r/p/mediawiki/extensions/Scribunto.git --depth 1
