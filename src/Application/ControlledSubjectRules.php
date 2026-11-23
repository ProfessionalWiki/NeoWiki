<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceResolver;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataSerializer;

/**
 * A Subject whose Schema does not resolve is not controlled.
 */
readonly class ControlledSubjectRules {

	public const string RIGHT = 'neowiki-controlled-subject-edit';
	public const string SUBJECT_EDIT_REFUSED = 'neowiki-controlled-subject-edit-refused';
	public const string DECLARATION_CHANGE_REFUSED = 'neowiki-controlled-declaration-change-refused';

	public function __construct(
		private SchemaReferenceResolver $schemaResolver,
		private SubjectContentDataSerializer $serializer,
	) {
	}

	/**
	 * The Schema of a Controlled Subject added, changed or removed going from $before to $after; null when there is
	 * none. A Subject counts by the Schema it names on the side that holds it, and a changed one by both.
	 */
	public function controlledSchemaChangedBy( SubjectMap $before, SubjectMap $after ): ?SchemaName {
		foreach ( $this->schemasOfChangedSubjects( $before, $after ) as $reference ) {
			$schema = $this->schemaResolver->getSchema( $reference );

			if ( $schema !== null && $schema->subjectsAreControlled() ) {
				return $schema->getName();
			}
		}

		return null;
	}

	/**
	 * @return iterable<SchemaReference>
	 */
	private function schemasOfChangedSubjects( SubjectMap $before, SubjectMap $after ): iterable {
		foreach ( $before->asArray() as $subjectBefore ) {
			$subjectAfter = $after->getSubject( $subjectBefore->id );

			if ( $subjectAfter !== null && $this->storedFormOf( $subjectAfter ) === $this->storedFormOf( $subjectBefore ) ) {
				continue;
			}

			yield $subjectBefore->getSchemaReference();

			if ( $subjectAfter !== null ) {
				yield $subjectAfter->getSchemaReference();
			}
		}

		foreach ( $after->asArray() as $subjectAfter ) {
			if ( !$before->hasSubject( $subjectAfter->id ) ) {
				yield $subjectAfter->getSchemaReference();
			}
		}
	}

	/**
	 * The Schema of a Controlled Subject without a label among $subjects, the Subjects of one page; null when there is
	 * none. Moving the page can rename such a Subject: a page's title names its Main Subject while that has no label, and
	 * making a Subject the Main Subject needs no right.
	 */
	public function controlledSchemaAPageMoveCanRename( SubjectMap $subjects ): ?SchemaName {
		foreach ( $subjects->asArray() as $subject ) {
			$schema = $subject->label === null ? $this->schemaResolver->getSchema( $subject->getSchemaReference() ) : null;

			if ( $schema !== null && $schema->subjectsAreControlled() ) {
				return $schema->getName();
			}
		}

		return null;
	}

	/**
	 * Compared as stored rather than with ==, which reads "0042" and "42.0" as the same number.
	 */
	private function storedFormOf( Subject $subject ): string {
		return $this->serializer->serialize( new PageSubjects( null, new SubjectMap( $subject ) ) );
	}

	/**
	 * A null $before or $after is a Schema that does not exist.
	 */
	public function changesDeclaration( ?Schema $before, ?Schema $after ): bool {
		return ( $before?->subjectsAreControlled() ?? false ) !== ( $after?->subjectsAreControlled() ?? false );
	}

}
