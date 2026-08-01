<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use Exception;
use Maintenance;
use ProfessionalWiki\NeoWiki\Application\Rdf\PageProjector;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageLoader;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfStreamWriter;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

class DumpRdf extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Streams an RDF projection of every page on the wiki to stdout as TriG, one named graph per '
			. 'page. Progress is written to stderr so stdout stays a clean RDF document.'
		);
		$this->addOption(
			'projection',
			'RDF projection to produce: "native" (default) or the name of a Mapping page (e.g. "EDM").',
			false,
			true
		);
	}

	public function execute(): void {
		$extension = NeoWikiExtension::getInstance();

		$projectionName = $this->getOption( 'projection', RdfPageProjector::PROJECTION );
		$projection = $extension->newRdfProjection( $projectionName );

		if ( $projection === null ) {
			$this->fatalError(
				'Unknown RDF projection: "' . $projectionName . '". Known projections: '
				. implode( ', ', $extension->getRdfProjectionNames() ) . '.'
			);
		}

		$loader = $extension->newRdfPageLoader();
		$projector = $projection->projector;
		$writer = $projection->serializer->newWriter( RdfFormat::TriG );

		$this->error( 'Dumping RDF...' );

		$dumped = 0;
		$total = 0;

		foreach ( $extension->newPageIdsLookup()->getPageIds() as $pageId ) {
			$total++;

			if ( $this->dumpPage( $pageId, $loader, $projector, $writer ) ) {
				$dumped++;
			}
		}

		$this->output( $writer->finish() );
		$this->error( "Dumped $dumped of $total pages." );
	}

	/**
	 * A page that cannot be loaded or projected is reported to stderr and skipped, as on the rebuild
	 * path: one unreadable page out of a wiki must not truncate the dump into an invalid document.
	 */
	private function dumpPage(
		int $pageId,
		RdfPageLoader $loader,
		PageProjector $projector,
		RdfStreamWriter $writer
	): bool {
		try {
			$page = $loader->loadByPageId( new PageId( $pageId ) );
		} catch ( Exception $e ) {
			$this->error( "Skipped page $pageId: " . $e->getMessage() );
			return false;
		}

		if ( $page === null ) {
			$this->error( "Skipped page $pageId: it no longer exists or its subject slot does not hold Subject data" );
			return false;
		}

		try {
			$quads = $projector->projectPage( $page );
		} catch ( Exception $e ) {
			$this->error( "Skipped page $pageId: " . $e->getMessage() );
			return false;
		}

		$this->output( $writer->write( $quads ) );

		return true;
	}

}

$maintClass = DumpRdf::class;
require_once RUN_MAINTENANCE_IF_MAIN;
