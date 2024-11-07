<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject;

use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;

readonly class PatchSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectAuthorizer $subjectActionAuthorizer,
		private StatementListPatcher $patcher
	) {
	}

	/**
	 * The patch maps property name to scalar value representation (or null to delete the statement).
	 * This follows the JSON Merge Patch specification (RFC 7396).
	 *
	 * @param SubjectId $subjectId
	 * @param string|null $label
	 * @param array<string, mixed> $patch
	 */
	public function patch(SubjectId $subjectId, ?string $label, array $patch): void {
		if (!$this->subjectActionAuthorizer->canEditSubject()) {
			throw new \RuntimeException('You do not have the necessary permissions to edit this subject');
		}

		$subject = $this->subjectRepository->getSubject($subjectId);

		if ($subject === null) {
			throw new \RuntimeException('Subject not found: ' . $subjectId->text);
		}

		if ($label !== null) {
			$subject->patchLabel( new SubjectLabel($label) );
		}

		$subject->patchStatements($this->patcher, $patch);

		$this->subjectRepository->updateSubject($subject);
	}

}
