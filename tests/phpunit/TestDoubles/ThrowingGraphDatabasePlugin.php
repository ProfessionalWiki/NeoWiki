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

	/**
	 * @param string $message What the backend reports on failure. A real client quotes the connection
	 *        URI it tried, so tests about how NeoWiki relays that message pass one in.
	 */
	public function __construct(
		private readonly string $message = self::FAILURE_MESSAGE
	) {
	}

	public function initialize(): void {
		throw new RuntimeException( $this->message );
	}

	public function savePage( Page $page ): void {
		throw new RuntimeException( $this->message );
	}

	public function deletePage( PageId $pageId ): void {
		throw new RuntimeException( $this->message );
	}

}
