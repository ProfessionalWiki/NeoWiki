<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\QueryStore;
use ProfessionalWiki\NeoWiki\Domain\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\SubjectMap;

class SpyQueryStore implements QueryStore {

	private array $savedPages = [];
	private array $deletedPages = [];
	private array $readQueries = [];

	public function savePage( int $pageId, string $pageTitle, SubjectMap $subjects ): void {
		$this->savedPages[] = [
			'pageId' => $pageId,
			'pageTitle' => $pageTitle,
			'subjects' => $subjects,
		];
	}

	public function deletePage( int $pageId ): void {
		$this->deletedPages[] = $pageId;
	}

	public function runReadQuery( string $cypher ): SummarizedResult {
		$this->readQueries[] = $cypher;
		$summary = null;
		return new SummarizedResult( $summary, [] );
	}

	public function getPageIdForSubject( SubjectId $subjectId ): ?int {
		return 0;
	}

	public function getSavedPages(): array {
		return $this->savedPages;
	}

	public function getDeletedPages(): array {
		return $this->deletedPages;
	}

	public function getReadQueries(): array {
		return $this->readQueries;
	}

}
