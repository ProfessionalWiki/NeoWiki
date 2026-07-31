<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class InMemorySubjectLookup implements SubjectLookup {

	/**
	 * @var array<string, Subject>
	 */
	private array $subjects = [];

	public int $getSubjectsCallCount = 0;

	public int $getSubjectCallCount = 0;

	public function __construct( Subject ...$subjects ) {
		foreach ( $subjects as $subject ) {
			$this->subjects[$subject->id->text] = $subject;
		}
	}

	public function getSubject( SubjectId $subjectId ): ?Subject {
		$this->getSubjectCallCount++;

		return $this->subjects[$subjectId->text] ?? null;
	}

	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap {
		$this->getSubjectsCallCount++;

		$found = new SubjectMap();

		foreach ( $subjectIds->asStringArray() as $idText ) {
			if ( array_key_exists( $idText, $this->subjects ) ) {
				$found->addOrUpdateSubject( $this->subjects[$idText] );
			}
		}

		return $found;
	}

}
