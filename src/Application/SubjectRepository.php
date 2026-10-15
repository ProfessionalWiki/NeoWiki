<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Application\Subject\Exception\DependentSubjectRulesViolatedException;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;

/**
 * Every write but {@see self::restorePageSubjects()} refuses to break a rule for Dependent Subjects, and removes the
 * Dependent Subjects it leaves unreached.
 */
interface SubjectRepository extends SubjectLookup {

	/**
	 * Puts $subject and $dependentSubjects on $subject's page in one revision, each replacing the Subject with
	 * its id. Answers ERROR when no page holds $subject.
	 *
	 * @throws DependentSubjectRulesViolatedException
	 */
	public function updateSubject(
		Subject $subject,
		?string $comment = null,
		SubjectMap $dependentSubjects = new SubjectMap()
	): PageContentSavingStatus;

	/**
	 * Removing a Dependent Subject also removes the Relations on its page that point at it.
	 *
	 * Answers REVISION_CREATED only when the Subject was actually removed. The index can name a page
	 * whose slot no longer holds it, and the page can go away under the write; both answer
	 * NO_CHANGES or ERROR rather than reporting a deletion that did not happen.
	 */
	public function deleteSubject( SubjectId $id, ?string $comment ): PageContentSavingStatus;

	/**
	 * TODO: document exceptions
	 */
	public function getSubjectsByPageId( PageId $pageId ): PageSubjects;

	/**
	 * Returns the outcome of the save. A PageContentSavingStatus::ERROR means the write did not land
	 * - most notably when the target page no longer resolves - so callers can avoid reporting success
	 * for a write that was silently dropped.
	 *
	 * @throws DependentSubjectRulesViolatedException
	 */
	public function savePageSubjects( PageSubjects $pageSubjects, PageId $pageId, ?string $comment = null ): PageContentSavingStatus;

	/**
	 * Writes $pageSubjects as given, without the rules for Dependent Subjects: for putting back what the page held.
	 */
	public function restorePageSubjects( PageSubjects $pageSubjects, PageId $pageId, ?string $comment ): PageContentSavingStatus;

	/**
	 * Creates the page $pageTitle names and puts $pageSubjects on it, in one revision. The page a
	 * Subject gets to itself carries no wikitext: what it says about the Subject is rendered from
	 * the Subject slot.
	 *
	 * A page that already exists is refused rather than edited, so a page that appeared since the
	 * caller last looked - which is what reading a replica leaves room for - cannot silently
	 * receive the Subject. The status is ERROR then, as it is for any write that did not land.
	 *
	 * @throws DependentSubjectRulesViolatedException
	 */
	public function createPageWithSubjects( string $pageTitle, PageSubjects $pageSubjects, ?string $comment = null ): PageContentSavingStatus;

}
