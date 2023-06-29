<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject;

use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Infrastructure\SubjectActionAuthorizer;

class PatchSubjectAction {
	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectActionAuthorizer $subjectActionAuthorizer
	) {
	}

	/**
	 * @param SubjectId $subjectId
	 * @param array<string, array> $patch Property name to list of new values
	 */
	public function patch( SubjectId $subjectId, array $patch ): void {
		if ( !$this->subjectActionAuthorizer->canEditSubject() ) {
			throw new \RuntimeException( 'You do not have the necessary permissions to edit this subject' );
		}

		$subject = $this->subjectRepository->getSubject( $subjectId );

		if ( $subject === null ) {
			throw new \RuntimeException( 'Subject not found: ' . $subjectId->text );
		}

		$subject->applyPatch( $patch );
		$this->subjectRepository->updateSubject( $subject );
	}

}
