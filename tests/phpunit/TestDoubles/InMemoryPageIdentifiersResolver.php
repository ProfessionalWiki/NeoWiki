<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersResolver;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;

class InMemoryPageIdentifiersResolver implements PageIdentifiersResolver {

	/**
	 * @var array<int, PageIdentifiers>
	 */
	private array $pageIdentifiers = [];

	/**
	 * @param PageIdentifiers[] $pageIdentifiers
	 */
	public function __construct( array $pageIdentifiers = [] ) {
		foreach ( $pageIdentifiers as $identifiers ) {
			$this->addIdentifiers( $identifiers );
		}
	}

	public function addIdentifiers( PageIdentifiers $pageIdentifiers ): void {
		$this->pageIdentifiers[$pageIdentifiers->getId()->id] = $pageIdentifiers;
	}

	public function getIdentifiersOfPage( PageId $pageId ): ?PageIdentifiers {
		return $this->pageIdentifiers[$pageId->id] ?? null;
	}

}
