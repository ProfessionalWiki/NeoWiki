<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject;

use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;

class DeleteSubjectAction {
	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectAuthorizer $subjectActionAuthorizer
	) {
	}

	public function deleteSubject( SubjectId $subjectId ): void {
		if ( !$this->subjectActionAuthorizer->canDeleteSubject() ) {
			throw new \RuntimeException( 'You do not have the necessary permissions to delete this subject' );
		}

		$this->subjectRepository->deleteSubject( $subjectId );
	}

}
