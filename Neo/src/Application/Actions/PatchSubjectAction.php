<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions;

use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\SubjectId;

class PatchSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository
	) {
	}

	public function patch( SubjectId $subjectId, array $patch ): void {
		$subject = $this->subjectRepository->getSubject( $subjectId );

		// TODO: apply patch
	}

}
