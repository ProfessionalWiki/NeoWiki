<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Application\PageSubjectsAtRevisionLookup;
use ProfessionalWiki\NeoWiki\Application\Validation\PageViolationChecker;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\ViolationIndex;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;

class TestPageViolationChecker {

	/**
	 * A checker for pages that all publish the given Subjects, validated against the given Schemas.
	 */
	public static function build(
		ViolationIndex $violationIndex,
		PageSubjects $pageSubjects = new PageSubjects( null, new SubjectMap() ),
		InMemorySchemaLookup $schemaLookup = new InMemorySchemaLookup(),
	): PageViolationChecker {
		return new PageViolationChecker(
			pageSubjectsLookup: new class( $pageSubjects ) implements PageSubjectsAtRevisionLookup {

				public function __construct( private readonly PageSubjects $pageSubjects ) {
				}

				public function getPageSubjects( PageId $pageId ): PageSubjects {
					return $this->pageSubjects;
				}

			},
			validator: new ProposedSubjectValidator(
				schemaResolver: TestSources::newSchemaResolver( $schemaLookup ),
				subjectValidator: new SubjectValidator(
					propertyTypeLookup: TestSources::newPropertyTypeRegistry(),
					subjectLookup: new InMemorySubjectLookup(),
					sourceRegistry: TestSources::newRegistry(),
				),
			),
			violationIndex: $violationIndex,
		);
	}

}
