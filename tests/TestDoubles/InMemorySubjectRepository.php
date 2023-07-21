<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class InMemorySubjectRepository implements SubjectRepository {

	/**
	 * @var array<string, Subject>
	 */
	private array $subjects = [];

	/**
	 * @var array<string, PageSubjects>
	 */
	private array $subjectsByPage = [];

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->subjects[$subjectId->text] ?? null;
	}

	public function updateSubject( Subject $subject ): void {
		$this->subjects[$subject->id->text] = $subject;
	}

	public function deleteSubject( SubjectId $id ): void {
		unset( $this->subjects[$id->text] );
	}

	public function getSubjectsByPageId( PageId $pageId ): PageSubjects {
		if ( array_key_exists( $pageId->id, $this->subjectsByPage ) ) {
			return $this->subjectsByPage[$pageId->id];
		}

		return PageSubjects::newEmpty();
	}

	public function savePageSubjects( PageSubjects $pageSubjects, PageId $pageId ): void {
		$this->subjectsByPage[$pageId->id] = $pageSubjects;

		foreach ( $pageSubjects->getAllSubjects() as $subject ) {
			$this->subjects[$subject->getId()->text] = $subject;
		}
	}

}
