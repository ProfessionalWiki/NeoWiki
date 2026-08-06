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

	/**
	 * Returns the Subjects in the order the constructor was given them, deliberately not in the
	 * order they were requested. SubjectLookup promises no ordering and neither implementation
	 * gives one - MediaWikiSubjectRepository unions per hosting page, PointInTimeSubjectLookup puts
	 * the primary revision's Subjects first - so a caller that reads the returned map in the map's
	 * own order rather than indexing it by the ids it asked for reorders its output in production.
	 * Mirroring the request here would hide exactly that, which is why tests asserting on response
	 * order register their Subjects in an order other than the one they reference them in.
	 */
	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap {
		$this->getSubjectsCallCount++;

		$requested = $subjectIds->asArray();
		$found = new SubjectMap();

		foreach ( $this->subjects as $idText => $subject ) {
			if ( array_key_exists( $idText, $requested ) ) {
				$found->addOrUpdateSubject( $subject );
			}
		}

		return $found;
	}

}
