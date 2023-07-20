<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject;

use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;
use ProfessionalWiki\NeoWiki\Infrastructure\SubjectActionAuthorizer;

class PatchSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectActionAuthorizer $subjectActionAuthorizer,
		private GuidGenerator $guidGenerator
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

		foreach ( $patch as $key => $value ) {
			/** @var mixed $value */
			if ( is_array( $value ) ) {
				$patch[$key] = array_map( function ( $item ) {
					if ( is_array( $item ) && isset( $item['target'] ) && !isset( $item['id'] ) ) {
						$item['id'] = RelationId::createNew( $this->guidGenerator )->asString();
					}
					return $item;
				}, $value );
			}
		}

		$subject->applyPatch( $patch );
		$this->subjectRepository->updateSubject( $subject );
	}

}
