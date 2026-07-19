<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableAccessException;
use ProfessionalWiki\NeoWiki\Application\Rdf\PageProjector;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageLoader;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfStreamWriter;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

class DumpRdf extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Streams an RDF projection of every subject page to stdout as TriG, one named graph per '
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

		$pageIds = $this->getSubjectPageIds();
		$this->error( 'Dumping RDF for ' . count( $pageIds ) . ' subject pages...' );

		$dumped = 0;

		foreach ( $pageIds as $pageId ) {
			if ( $this->dumpPage( $pageId, $loader, $projector, $writer ) ) {
				$dumped++;
			}
		}

		$this->output( $writer->finish() );
		$this->error( "Dumped $dumped of " . count( $pageIds ) . ' pages.' );
	}

	private function dumpPage(
		int $pageId,
		RdfPageLoader $loader,
		PageProjector $projector,
		RdfStreamWriter $writer
	): bool {
		$page = $loader->loadByPageId( new PageId( $pageId ) );

		if ( $page === null ) {
			return false;
		}

		$this->output( $writer->write( $projector->projectPage( $page ) ) );

		return true;
	}

	/**
	 * @return int[] Page IDs of every page whose latest revision carries the NeoWiki subject slot.
	 */
	private function getSubjectPageIds(): array {
		$services = MediaWikiServices::getInstance();

		try {
			$roleId = $services->getSlotRoleStore()->getId( MediaWikiSubjectRepository::SLOT_NAME );
		} catch ( NameTableAccessException ) {
			// No page has ever stored a Subject, so the slot role was never registered. There is
			// nothing to dump; emit an empty document instead of crashing.
			return [];
		}

		$rows = $this->getReplicaDB()->newSelectQueryBuilder()
			->select( 'page_id' )
			->from( 'page' )
			->join( 'slots', null, 'slot_revision_id = page_latest' )
			->where( [ 'slot_role_id' => $roleId ] )
			->orderBy( 'page_id' )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return array_map( 'intval', $rows );
	}

}

$maintClass = DumpRdf::class;
require_once RUN_MAINTENANCE_IF_MAIN;
