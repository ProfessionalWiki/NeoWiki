<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;

class InMemorySubjectContent extends SubjectContent {

	public function __construct( private readonly PageSubjects $pageSubjects ) {
		parent::__construct( '{}' );
	}

	public function getPageSubjects(): PageSubjects {
		return $this->pageSubjects;
	}

}
