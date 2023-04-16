<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class InMemorySubjectRepository implements SubjectRepository {

	/**
	 * @var array<string, Subject> Subjects indexed by their ID
	 */
	private array $subjects = [];

	/**
	 * @var array<int, Subject> Main subjects indexed by their page ID
	 */
	private array $mainSubjects = [];

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->subjects[$subjectId->text] ?? null;
	}

	public function updateSubject( Subject $subject ): void {
		$this->subjects[$subject->id->text] = $subject;
	}

	public function deleteSubject( SubjectId $id ): void {
		unset( $this->subjects[$id->text] );
	}

	public function getMainSubject( PageId $pageId ): ?Subject {
		return $this->mainSubjects[$pageId->id] ?? null;
	}

	public function setMainSubject( Subject $subject, PageId $pageId ): void {
		$this->updateSubject( $subject );
		$this->mainSubjects[$pageId->id] = $subject;
	}

	public function setChildSubject( Subject $subject, PageId $pageId ): void {
		$this->updateSubject( $subject );
	}

}
