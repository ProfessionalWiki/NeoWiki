<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Application\PageSubjectsAtRevisionLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Validation\SubjectViolation;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Persistence\ViolationIndex;

/**
 * Validates the Subjects a page publishes against their Schemas and records what it finds.
 */
readonly class PageViolationChecker {

	public function __construct(
		private PageSubjectsAtRevisionLookup $pageSubjectsLookup,
		private ProposedSubjectValidator $validator,
		private ViolationIndex $violationIndex,
	) {
	}

	public function checkPage( PageId $pageId ): void {
		$violations = [];

		foreach ( $this->pageSubjectsLookup->getPageSubjects( $pageId )->getAllSubjects()->asArray() as $subject ) {
			foreach ( $this->validator->validate( $subject ) as $violation ) {
				$violations[] = new SubjectViolation(
					subjectId: $subject->id,
					schemaName: $subject->getSchemaReference()->name->getText(),
					violation: $violation,
					relationTarget: self::relationTargetOf( $subject, $violation ),
				);
			}
		}

		$this->violationIndex->setViolationsOfPage( $pageId, ...$violations );
	}

	private static function relationTargetOf( Subject $subject, Violation $violation ): ?SubjectId {
		if ( $violation->propertyName === null || $violation->valuePartIndex === null ) {
			return null;
		}

		$value = $subject->getStatements()->getStatement( $violation->propertyName )?->getValue();

		if ( !$value instanceof RelationValue ) {
			return null;
		}

		return ( $value->relations[$violation->valuePartIndex] ?? null )?->targetId;
	}

}
