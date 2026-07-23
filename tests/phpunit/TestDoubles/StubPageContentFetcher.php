<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Content\Content;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentFetcher;
use RuntimeException;

/**
 * A PageContentFetcher that returns a fixed Content, records how many times it was asked, and can
 * simulate the database being unavailable by throwing.
 */
class StubPageContentFetcher extends PageContentFetcher {

	public int $fetchCount = 0;

	public function __construct(
		private readonly ?Content $content,
		private readonly bool $throw = false,
	) {
	}

	public function getPageContent(
		string|Title $pageTitle,
		Authority $authority,
		int $defaultNamespace = NS_MAIN,
		string $slotName = SlotRecord::MAIN
	): ?Content {
		$this->fetchCount++;

		if ( $this->throw ) {
			throw new RuntimeException( 'Database unavailable' );
		}

		return $this->content;
	}

}
