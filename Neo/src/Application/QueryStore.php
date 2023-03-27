<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Domain\PageInfo;
use ProfessionalWiki\NeoWiki\Domain\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\SubjectMap;

interface QueryStore {

	public function savePage( int $pageId, PageInfo $pageInfo, SubjectMap $subjects ): void;

	public function deletePage( int $pageId ): void;

	public function runReadQuery( string $cypher ): SummarizedResult;

	public function getPageIdForSubject( SubjectId $subjectId ): ?int;

}
