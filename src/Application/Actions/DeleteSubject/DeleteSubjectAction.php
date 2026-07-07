<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use RuntimeException;

readonly class DeleteSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectAuthorizer $subjectAuthorizer,
		private PageIdentifiersLookup $pageIdentifiersLookup
	) {
	}

	public function deleteSubject( SubjectId $subjectId, ?string $comment ): void {
		// A null pageId (unresolvable Subject) makes the authorizer fall back to the global 'edit' right.
		// This cannot bypass page protection: the repository resolves the page via the same lookup, so an
		// unresolvable Subject results in a no-op delete rather than a write to a protected page.
		$pageId = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId )?->getId();

		if ( !$this->subjectAuthorizer->canDeleteSubject( $pageId ) ) {
			throw new RuntimeException( 'You do not have the necessary permissions to delete this subject' );
		}

		$this->subjectRepository->deleteSubject( $subjectId, $comment );
	}

}
