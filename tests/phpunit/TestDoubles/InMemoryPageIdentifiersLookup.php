<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;

class InMemoryPageIdentifiersLookup implements PageIdentifiersLookup {

	private array $pageIdentifiers = [];

	public int $getPageIdsOfSubjectsCallCount = 0;

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

	/**
	 * @return array<string, PageIdentifiers>
	 */
	public function getPageIdsOfSubjects( SubjectIdList $subjectIds ): array {
		$this->getPageIdsOfSubjectsCallCount++;

		$found = [];

		foreach ( $subjectIds->asStringArray() as $idText ) {
			if ( array_key_exists( $idText, $this->pageIdentifiers ) ) {
				$found[$idText] = $this->pageIdentifiers[$idText];
			}
		}

		return $found;
	}

}
