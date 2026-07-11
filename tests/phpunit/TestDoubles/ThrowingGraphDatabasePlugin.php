<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use RuntimeException;

/**
 * Stands in for a graph backend that is down: every projection write throws,
 * the way an unreachable Neo4j or SPARQL endpoint does.
 */
class ThrowingGraphDatabasePlugin implements GraphDatabasePlugin {

	public const string FAILURE_MESSAGE = 'projection backend unreachable';

	public function savePage( Page $page ): void {
		throw new RuntimeException( self::FAILURE_MESSAGE );
	}

	public function deletePage( PageId $pageId ): void {
		throw new RuntimeException( self::FAILURE_MESSAGE );
	}

}
