<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject;

use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Infrastructure\SubjectActionAuthorizer;

class PatchSubjectAction {

	public function __construct(
		private readonly SubjectRepository $subjectRepository,
		private readonly SubjectActionAuthorizer $subjectActionAuthorizer,
		private readonly StatementListPatcher $patcher
	) {
	}

	/**
	 * The patch maps property name to scalar value representation (or null to delete the statement).
	 * This follows the JSON Merge Patch specification (RFC 7396).
	 *
	 * @param SubjectId $subjectId
	 * @param array<string, mixed> $patch
	 */
	public function patch( SubjectId $subjectId, array $patch ): void {
		if ( !$this->subjectActionAuthorizer->canEditSubject() ) {
			throw new \RuntimeException( 'You do not have the necessary permissions to edit this subject' );
		}

		$subject = $this->subjectRepository->getSubject( $subjectId );

		if ( $subject === null ) {
			throw new \RuntimeException( 'Subject not found: ' . $subjectId->text );
		}

		$subject->patchStatements( $this->patcher, $patch );

		$this->subjectRepository->updateSubject( $subject );
	}

}
