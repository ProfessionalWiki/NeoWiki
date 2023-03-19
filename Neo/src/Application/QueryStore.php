<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Domain\Subject;

interface QueryStore {

	public function saveSubject( Subject $subject ): void;

	public function runReadQuery( string $cypher ): SummarizedResult;

}
