<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use Exception;
use MediaWiki\Maintenance\Maintenance;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\NeoWikiTables;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

class Check extends Maintenance {

	private const array FRONTEND_BUILD = [
		'resources/ext.neowiki/dist/neowiki.js',
		'resources/ext.neowiki/dist/neowiki.css',
	];

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Reports what this NeoWiki install is missing, and exits with an error if anything is. Changes no wiki data, ' .
			'though like update.php it may create the constraints a graph store needs.'
		);
	}

	public function execute(): bool {
		$checksPassed = [
			$this->checkFrontendBuild(),
			$this->checkTables(),
			$this->checkNeo4jConfiguration(),
			$this->checkGraphStores(),
		];

		if ( in_array( false, $checksPassed, true ) ) {
			$this->output( "\nFix the problems above, then run this again.\n" );
			return false;
		}

		$this->output( "\nNothing is missing.\n" );
		return true;
	}

	private function checkFrontendBuild(): bool {
		$directory = $this->getExtensionDirectory();
		$missing = array_filter(
			array_map( static fn ( string $path ): string => $directory . '/' . $path, self::FRONTEND_BUILD ),
			static fn ( string $path ): bool => !is_file( $path )
		);

		if ( $missing === [] ) {
			$this->output( "Frontend build: present\n" );
			return true;
		}

		$this->output(
			'Frontend build: missing ' . implode( ', ', $missing ) . "\n" .
			"  Install NeoWiki from a release tarball, or run npm ci && npm run build in $directory/resources/ext.neowiki.\n"
		);
		return false;
	}

	protected function getExtensionDirectory(): string {
		return dirname( __DIR__ );
	}

	private function checkTables(): bool {
		$missing = $this->newTables()->getMissing();

		if ( $missing === [] ) {
			$this->output( "Database tables: present\n" );
			return true;
		}

		$this->output(
			'Database tables: missing ' . implode( ', ', $missing ) . "\n" .
			"  Run php maintenance/run.php update.\n"
		);
		return false;
	}

	protected function newTables(): NeoWikiTables {
		return new NeoWikiTables( $this->getPrimaryDB(), NeoWikiTables::NAMES );
	}

	private function checkNeo4jConfiguration(): bool {
		if ( !NeoWikiExtension::getInstance()->config->hasHalfConfiguredNeo4j() ) {
			return true;
		}

		$this->output(
			'Neo4j: only one of $wgNeoWikiNeo4jInternalReadUrl and $wgNeoWikiNeo4jInternalWriteUrl is set' . "\n" .
			"  Set both, or neither to run without Neo4j.\n"
		);
		return false;
	}

	private function checkGraphStores(): bool {
		try {
			$stores = NeoWikiExtension::getInstance()->getNamedGraphDatabasePlugins();
		} catch ( Exception $e ) {
			$this->reportStoreError( 'Graph stores', $e );
			return false;
		}

		if ( $stores === [] ) {
			$this->output( "Graph stores: none configured\n" );
			return true;
		}

		$allReached = true;

		foreach ( $stores as $storeName => $store ) {
			try {
				$store->initialize();
				$this->output( "Graph store $storeName: reachable\n" );
			} catch ( Exception $e ) {
				$this->reportStoreError( "Graph store $storeName", $e );
				$allReached = false;
			}
		}

		return $allReached;
	}

	private function reportStoreError( string $label, Exception $e ): void {
		$this->output( "$label: error: " . BackendFailureMessage::withoutCredentials( $e->getMessage() ) . "\n" );
	}

}

$maintClass = Check::class;
require_once RUN_MAINTENANCE_IF_MAIN;
