<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject;

use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectPatchResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use RuntimeException;

class PatchSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectAuthorizer $subjectAuthorizer,
		private StatementListPatcher $patcher,
		private SchemaLookup $schemaLookup,
		private SelectPatchResolver $selectPatchResolver,
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
	public function patch( SubjectId $subjectId, ?string $label, array $patch, ?string $comment = null ): void {
		if ( !$this->subjectAuthorizer->canEditSubject() ) {
			throw new RuntimeException( 'You do not have the necessary permissions to edit this subject' );
		}

		$subject = $this->subjectRepository->getSubject( $subjectId );

		if ( $subject === null ) {
			throw new RuntimeException( 'Subject not found: ' . $subjectId->text );
		}

		if ( $label !== null ) {
			$subject->setLabel( new SubjectLabel( $label ) );
		}

		$subject->patchStatements( $this->patcher, $this->resolvePatch( $subject, $patch ) );

		$this->subjectRepository->updateSubject( $subject, $comment );
	}

	/**
	 * @param array<string, mixed> $patch
	 *
	 * @return array<string, mixed>
	 */
	private function resolvePatch( Subject $subject, array $patch ): array {
		$schema = $this->schemaLookup->getSchema( $subject->getSchemaName() );

		if ( $schema === null ) {
			return $patch;
		}

		return $this->selectPatchResolver->resolve( $schema, $patch );
	}

}
