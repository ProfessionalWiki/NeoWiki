<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;

class InMemorySubjectRepository implements SubjectRepository {

	/**
	 * @var array<string, Subject> Subjects indexed by their ID
	 */
	private array $subjects = [];

	/**
	 * @var array<int, Subject> Main subjects indexed by their page ID
	 */
	private array $mainSubjects = [];

	/**
	 * @var array<string, PageSubjects> PageSubjects indexed by their page ID
	 */
	private array $pageSubjects = [];

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

	public function getPageSubjects( PageId $pageId ): PageSubjects {
		$pageIdString = (string)$pageId;

		if ( !isset( $this->pageSubjects[$pageIdString] ) ) {
			$this->pageSubjects[$pageIdString] = PageSubjects::newEmpty();
		}

		return $this->pageSubjects[$pageIdString];
	}

	public function savePageSubjects( PageSubjects $pageSubjects, PageId $pageId ): void {
		$pageIdString = (string)$pageId;
		$this->pageSubjects[$pageIdString] = $pageSubjects;
	}
	
}
