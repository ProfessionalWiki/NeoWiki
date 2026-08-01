<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Persistence\DeletedSubjectPageIdsLookup;

class InMemoryDeletedSubjectPageIdsLookup implements DeletedSubjectPageIdsLookup {

	/**
	 * @var int[]
	 */
	private array $pageIds;

	public function __construct( int ...$pageIds ) {
		$this->pageIds = $pageIds;
	}

	public function getDeletedSubjectPageIds(): array {
		return $this->pageIds;
	}

}
