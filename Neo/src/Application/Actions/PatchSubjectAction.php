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

	/**
	 * @param SubjectId $subjectId
	 * @param array<string, array> $patch Property name to list of new values
	 *
	 * @return void
	 */
	public function patch( SubjectId $subjectId, array $patch ): void {
		$subject = $this->subjectRepository->getSubject( $subjectId );

		// TODO: apply patch
	}

}
