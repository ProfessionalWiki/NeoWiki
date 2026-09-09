<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;

readonly class DeleteSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private PageReadAuthorizer $readAuthorizer,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private PageIdentifiersLookup $pageIdentifiersLookup
	) {
	}

	public function deleteSubject( SubjectId $subjectId, ?string $comment ): void {
		$pageId = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId )?->getId();

		// Gate on read before write: a Subject on a page the caller may not read answers exactly like
		// one that does not exist, and so does a Subject on no page, which has no page rights to
		// check. Reaching the write check first would answer 403 where a restricted page answers
		// 404. See PageReadAuthorizer for why a denied read takes the not-found shape.
		if ( $pageId === null || !$this->readAuthorizer->authorizeReadByPageId( $pageId ) ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		if ( !$this->writeAuthorizer->authorize( $pageId ) ) {
			throw new SubjectEditNotAuthorizedException( 'You do not have the necessary permissions to delete this subject' );
		}

		$status = $this->subjectRepository->deleteSubject( $subjectId, $comment );

		// Only a new revision means the Subject was removed. The index can name a page whose slot no
		// longer holds it, and the page can go away between the checks above and the write; either
		// way the Subject the caller named is not there, which is what an absent one answers.
		if ( $status->status !== PageContentSavingStatus::REVISION_CREATED ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}
	}

}
