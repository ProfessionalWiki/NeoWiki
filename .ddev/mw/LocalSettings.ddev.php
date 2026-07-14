<?php
# LocalSettings for the ddev dev environment. Docker/mediawiki/LocalSettings.php
# (written by .ddev/setup/install-wiki.sh) requires this file.
# Derived from Docker/SettingsTemplate.php, adapted for ddev: flat docroot
# (no /w script path), ddev DB credentials, Mailpit, DDEV_PRIMARY_URL.

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

error_reporting( -1 );
ini_set( 'display_errors', '1' );
$wgShowExceptionDetails = true;
$wgDebugDumpSql = true;
$wgDebugComments = true;

$wgSitename = 'NeoWiki Dev';

## ddev serves MediaWiki from the docroot directly (no /w prefix).
$wgScriptPath = '';
$wgArticlePath = '/index.php/$1';
$wgUsePathInfo = true;

## ddev provides the canonical project URL, e.g. https://neowiki.ddev.site
$wgServer = getenv( 'DDEV_PRIMARY_URL' );

$wgResourceBasePath = $wgScriptPath;
$wgLogos = [ '1x' => "$wgResourceBasePath/resources/assets/wiki.png" ];
$wgFavicon = "$wgScriptPath/images/favicon.ico";

$wgEnableEmail = true;
$wgEnableUserEmail = true;
$wgEmergencyContact = 'apache@localhost';
$wgPasswordSender = 'apache@localhost';
$wgEnotifUserTalk = false;
$wgEnotifWatchlist = false;
$wgEmailAuthentication = false;

## ddev's fixed in-container database credentials.
$wgDBtype = 'mysql';
$wgDBserver = 'db';
$wgDBname = 'db';
$wgDBuser = 'db';
$wgDBpassword = 'db';
$wgDBprefix = '';
$wgDBTableOptions = 'ENGINE=InnoDB, DEFAULT CHARSET=binary';
$wgSharedTables[] = 'actor';

## Dev mode: no caching, full debug (mirrors SettingsTemplate.php dev branch).
$wgMainCacheType = CACHE_NONE;
$wgMessageCacheType = CACHE_NONE;
$wgLanguageConverterCacheType = CACHE_NONE;
$wgSessionCacheType = CACHE_DB;
$wgParserCacheType = CACHE_NONE;
$wgRevisionCacheExpiry = 0;
$wgUseLocalMessageCache = true;

$wgEnableUploads = true;
$wgUseImageMagick = true;
$wgImageMagickConvertCommand = '/usr/bin/convert';
$wgUseInstantCommons = false;
$wgPingback = false;
$wgShellLocale = 'C.UTF-8';
$wgCacheDirectory = "$IP/cache";

$wgLanguageCode = 'en';

# Dev-only keys (identical role to the ones in SettingsTemplate.php).
$wgSecretKey = '84f2483d1826a014b0c70060187b2ff88c1650ff9538977d96a4fa5404f9304a';
$wgAuthenticationTokenVersion = '1';
$wgUpgradeKey = 'be55e528a693624f';

$wgRightsPage = '';
$wgRightsUrl = '';
$wgRightsText = '';
$wgRightsIcon = '';

$wgDiff3 = '/usr/bin/diff3';

$wgMetaNamespace = 'Wiki';

$wgDefaultSkin = 'vector-2022';
wfLoadSkin( 'MonoBook' );
wfLoadSkin( 'Timeless' );
wfLoadSkin( 'Vector' );

wfLoadExtension( 'WikiEditor' );
wfLoadExtension( 'VisualEditor' );
wfLoadExtension( 'CodeEditor' );
wfLoadExtension( 'SyntaxHighlight_GeSHi' );
wfLoadExtension( 'Scribunto' );
wfLoadExtension( 'ParserFunctions' );

## Mail is caught by ddev's built-in Mailpit (UI URL: `ddev describe`).
$wgSMTP = [ 'host' => '127.0.0.1', 'port' => 1025 ];

wfLoadExtension( 'NeoWiki' );
wfLoadExtension( 'RedHerb', "$IP/extensions/NeoWiki/tests/RedHerb/extension.json" );

$wgNeoWikiEnableDevelopmentUI = true;

# The mediawiki_read user is provisioned by a post-start hook (.ddev/config.yaml).
$wgNeoWikiNeo4jInternalWriteUrl = 'bolt://neo4j:password@neo:7687';
$wgNeoWikiNeo4jInternalReadUrl = 'bolt://mediawiki_read:mediawiki_read@neo:7687';

// Allow anonymous REST API calls on the wiki.
$wgCrossSiteAJAXdomains = [ '*' ];

// Expose MediaWiki core's OpenAPI spec endpoints (T365753).
$wgRestAPIAdditionalRouteFiles[] = 'includes/Rest/specs.v0.json';

$wgEmailConfirmToEdit = false;
$wgGroupPermissions['*']['edit'] = false;
$wgUseRCPatrol = false;
$wgUseNPPatrol = false;

# Per-checkout opt-in overrides (gitignored).
$mwLocal = __DIR__ . '/LocalSettings.local.php';
if ( file_exists( $mwLocal ) ) {
	require_once $mwLocal;
}
