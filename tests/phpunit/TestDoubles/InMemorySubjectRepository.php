<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;

class InMemorySubjectRepository implements SubjectRepository {

	/**
	 * @var array<string, Subject>
	 */
	private array $subjects = [];

	/**
	 * @var array<string, PageSubjects>
	 */
	private array $subjectsByPage = [];

	/**
	 * @var array<string, string|null>
	 */
	public array $comments = [];

	public int $updateSubjectCallCount = 0;

	/**
	 * When true, savePageSubjects reports a save failure without storing anything, standing in for a
	 * page that has gone away underneath the write (the branch PageContentSaver returns ERROR for).
	 */
	public bool $failNextSave = false;

	/**
	 * Page ids whose saves report the same failure as $failNextSave. Lets a test that writes several
	 * pages fail one of them and let the others through, which $failNextSave cannot express.
	 *
	 * @var int[]
	 */
	public array $failSavesForPageIds = [];

	/**
	 * The page ids passed to savePageSubjects, in call order, including the ones made to fail.
	 *
	 * @var int[]
	 */
	public array $savedPageIds = [];

	/**
	 * Fails every save from this 1-based call number onward, so a test can let the first write to a
	 * page through and fail a later one - which is what a compensating write that fails looks like.
	 */
	public ?int $failSaveFromCallNumber = null;

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->subjects[$subjectId->text] ?? null;
	}

	/**
	 * Returns the Subjects in the order this repository stored them, deliberately not in the order
	 * they were requested, for the reason {@see InMemorySubjectLookup::getSubjects} gives: the
	 * production implementations promise no order, and a double that mirrors the request hides
	 * callers that read the returned map in the map's own order. This class is a SubjectRepository,
	 * which extends SubjectLookup, and production hands one object to both roles, so a test is free
	 * to wire it as the subjectLookup.
	 */
	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap {
		$requested = $subjectIds->asArray();
		$found = new SubjectMap();

		foreach ( $this->subjects as $idText => $subject ) {
			if ( array_key_exists( $idText, $requested ) ) {
				$found->addOrUpdateSubject( $subject );
			}
		}

		return $found;
	}

	public function updateSubject( Subject $subject, ?string $comment = null ): void {
		$this->subjects[$subject->id->text] = $subject;
		$this->comments[$subject->id->text] = $comment;
		$this->updateSubjectCallCount++;
	}

	public function deleteSubject( SubjectId $id, ?string $comment ): void {
		unset( $this->subjects[$id->text] );
		$this->comments[$id->text] = $comment;
	}

	/**
	 * Answers with a fresh PageSubjects on every call, as production does: SubjectContent
	 * deserializes the slot anew per read, so a caller that mutates what it got back has changed
	 * nothing until it saves. Handing out the stored object instead would let a mutation that was
	 * never saved - or whose save failed - show up in the next read.
	 */
	public function getSubjectsByPageId( PageId $pageId ): PageSubjects {
		if ( array_key_exists( $pageId->id, $this->subjectsByPage ) ) {
			return self::copyOf( $this->subjectsByPage[$pageId->id] );
		}

		return PageSubjects::newEmpty();
	}

	/**
	 * Decouples which Subjects a page holds, which is what a caller that adds or removes them
	 * changes. The Subject objects themselves are shared, not copied: Subject is mutable, so a
	 * caller that edits one in place - as ReplaceSubjectAction does - still edits the stored one.
	 */
	private static function copyOf( PageSubjects $pageSubjects ): PageSubjects {
		return new PageSubjects( $pageSubjects->getMainSubject(), clone $pageSubjects->getChildSubjects() );
	}

	public function savePageSubjects( PageSubjects $pageSubjects, PageId $pageId, ?string $comment = null ): PageContentSavingStatus {
		$this->savedPageIds[] = $pageId->id;
		$callNumber = count( $this->savedPageIds );

		if ( $this->failNextSave
			|| in_array( $pageId->id, $this->failSavesForPageIds, true )
			|| ( $this->failSaveFromCallNumber !== null && $callNumber >= $this->failSaveFromCallNumber ) ) {
			return new PageContentSavingStatus( PageContentSavingStatus::ERROR, 'Page not found' );
		}

		$this->subjectsByPage[$pageId->id] = self::copyOf( $pageSubjects );
		$this->comments[$pageId->id] = $comment;

		foreach ( $pageSubjects->getAllSubjects()->asArray() as $subject ) {
			$this->subjects[$subject->getId()->text] = $subject;
		}

		return new PageContentSavingStatus( PageContentSavingStatus::REVISION_CREATED );
	}

}
