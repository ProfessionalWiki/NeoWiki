<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Page\PageIdentity;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;

interface SubjectContentRepository {

	public function getSubjectContentByPageId( PageId $pageId ): ?SubjectContent;

	public function getSubjectContentByPageTitle( PageIdentity $pageIdentity ): ?SubjectContent;

	public function getSubjectContentByRevisionId( int $revisionId ): ?SubjectContent;

	public function editSubjectContent(
		SubjectContent $subjectContent,
		PageId $pageId,
		string $editSummary
	): void;

}
