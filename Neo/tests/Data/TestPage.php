<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

/**
 * @see \ProfessionalWiki\NeoWiki\Domain\Page\Page
 */
class TestPage {

	public static function build(
		?int $id = null,
		PageProperties $properties = new PageProperties( 'PageTitle' ),
		?Subject $mainSubject = null,
		SubjectMap $childSubjects = new SubjectMap()
	): Page {
		return new Page(
			id: new PageId( $id ?? 31337 ),
			properties: $properties,
			mainSubject: $mainSubject,
			childSubjects: $childSubjects,
		);
	}

}
