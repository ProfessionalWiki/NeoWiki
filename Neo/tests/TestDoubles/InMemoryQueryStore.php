<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\QueryStore;
use ProfessionalWiki\NeoWiki\Domain\PageInfo;
use ProfessionalWiki\NeoWiki\Domain\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\SubjectMap;

class InMemoryQueryStore implements QueryStore {

	/**
	 * @var array<int, SubjectMap>
	 */
	private array $subjectsByPageId = [];

	public function savePage( int $pageId, PageInfo $pageInfo, SubjectMap $subjects ): void {
		$this->subjectsByPageId[$pageId] = $subjects;
	}

	public function deletePage( int $pageId ): void {
		unset( $this->subjectsByPageId[$pageId] );
	}

	public function runReadQuery( string $cypher ): SummarizedResult {
		$summary = null;
		return new SummarizedResult( $summary, [] );
	}

	public function getPageIdForSubject( SubjectId $subjectId ): ?int {
		foreach ( $this->subjectsByPageId as $pageId => $subjectMap ) {
			if ( $subjectMap->hasSubject( $subjectId ) ) {
				return $pageId;
			}
		}

		return null;
	}

}
