<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use Wikimedia\Rdbms\DBUnexpectedError;

/**
 * Stands in for a backend whose projection hits the wiki's own database — a page property provider
 * reading from it, say — at the moment that database gives out. Every page after this one would fail
 * the same way, which is what separates it from a page the store merely rejects.
 */
class DatabaseFailingGraphDatabasePlugin implements GraphDatabasePlugin {

	public const string FAILURE_MESSAGE = 'the wiki database is gone';

	public function initialize(): void {
	}

	public function savePage( Page $page ): void {
		throw new DBUnexpectedError( null, self::FAILURE_MESSAGE );
	}

	public function deletePage( PageId $pageId ): void {
	}

}
