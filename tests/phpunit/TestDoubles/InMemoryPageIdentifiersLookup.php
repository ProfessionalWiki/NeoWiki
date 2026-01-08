<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class InMemoryPageIdentifiersLookup implements PageIdentifiersLookup {

	private array $pageIdentifiers = [];

	/**
	 * @param array<int, array{0: SubjectId, 1: PageIdentifiers}> $subjectIdsAndPageIdentifiers
	 */
	public function __construct( array $subjectIdsAndPageIdentifiers = [] ) {
		foreach ( $subjectIdsAndPageIdentifiers as $entry ) {
			$this->addIdentifiers( $entry[0], $entry[1] );
		}
	}

	public function addIdentifiers( SubjectId $subjectId, PageIdentifiers $pageIdentifiers ): void {
		$this->pageIdentifiers[$subjectId->text] = $pageIdentifiers;
	}

	public function getPageIdOfSubject( SubjectId $subjectId ): ?PageIdentifiers {
		return $this->pageIdentifiers[$subjectId->text] ?? null;
	}

}
