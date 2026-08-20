<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use RuntimeException;

readonly class DeleteSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private PageIdentifiersLookup $pageIdentifiersLookup
	) {
	}

	public function deleteSubject( SubjectId $subjectId, ?string $comment ): void {
		$pageId = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId )?->getId();

		// A Subject on no page has no page rights to check, so it is answered as absent rather than as
		// forbidden.
		if ( $pageId === null ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		if ( !$this->writeAuthorizer->authorize( $pageId ) ) {
			throw new RuntimeException( 'You do not have the necessary permissions to delete this subject' );
		}

		$this->subjectRepository->deleteSubject( $subjectId, $comment );
	}

}
