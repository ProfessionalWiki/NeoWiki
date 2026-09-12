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

	public function getIdentifiersOfTitle( string $pageTitle ): ?PageIdentifiers {
		foreach ( $this->pageIdentifiers as $identifiers ) {
			if ( $identifiers->getTitle() === $pageTitle ) {
				return $identifiers;
			}
		}

		return null;
	}

	/**
	 * Of the wiki's normalization only the capital first letter, which is the part callers depend
	 * on: it is what makes a page titled after a Subject id reachable. Which other texts title no
	 * page is the wiki's own parsing, which this double does not reproduce.
	 */
	public function getMainNamespaceTitle( string $text ): ?string {
		$trimmed = trim( $text );

		return $trimmed === '' ? null : ucfirst( $trimmed );
	}

}
