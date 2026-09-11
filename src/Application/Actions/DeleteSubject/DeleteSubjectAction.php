<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject;

use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SubjectHostingPageResolver;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;

readonly class DeleteSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectHostingPageResolver $hostingPageResolver,
		private SubjectWriteAuthorizer $writeAuthorizer,
	) {
	}

	public function deleteSubject( SubjectId $subjectId, ?string $comment ): void {
		$pageId = $this->hostingPageResolver->resolveReadableHostingPage( $subjectId )?->getId();

		if ( $pageId === null ) {
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
