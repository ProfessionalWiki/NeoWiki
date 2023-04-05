<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject;

use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class DeleteSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository
	) {
	}

	public function deleteSubject( SubjectId $subjectId ): void {
		$this->subjectRepository->deleteSubject( $subjectId );
	}

}
