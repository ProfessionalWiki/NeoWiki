<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectIdInUseException;
use ProfessionalWiki\NeoWiki\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceParser;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceResolver;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

readonly class DependentSubjectBuilder {

	public function __construct(
		private NewSubjectIdResolver $newSubjectIdResolver,
		private SchemaReferenceParser $schemaReferenceParser,
		private SchemaReferenceResolver $schemaResolver,
		private SelectStatementResolver $selectStatementResolver,
		private StatementListBuilder $statementListBuilder,
		private DependentSubjectRules $dependentSubjectRules,
	) {
	}

	/**
	 * @param array<mixed> $items Each an object with an `id`, a `schema` and `statements`.
	 * @param PageSubjects $pageSubjects The page's Subjects before the write.
	 *
	 * @throws InvalidArgumentException When an item is malformed, repeats an id, or names a Schema that
	 *   does not declare its Subjects dependent.
	 * @throws SubjectIdInUseException When an item takes the id of another Subject, except a Dependent Subject
	 *   of the same Schema that the Host Subject reaches.
	 */
	public function build( array $items, SubjectId $hostSubjectId, PageSubjects $pageSubjects ): SubjectMap {
		$subjects = new SubjectMap();
		$replaceableSubjects = $this->replaceableSubjects( $hostSubjectId, $pageSubjects );

		foreach ( $items as $item ) {
			$subject = $this->buildSubject( $item );

			if ( $subjects->hasSubject( $subject->id ) || $subject->id->equals( $hostSubjectId ) ) {
				throw new InvalidArgumentException( "dependentSubjects holds the Subject ID {$subject->id->text} twice" );
			}

			if ( $this->takesTheIdOfAnotherSubject( $subject, $pageSubjects, $replaceableSubjects ) ) {
				throw new SubjectIdInUseException( $subject->id );
			}

			$subjects->addOrUpdateSubject( $subject );
		}

		return $subjects;
	}

	private function replaceableSubjects( SubjectId $hostSubjectId, PageSubjects $pageSubjects ): SubjectMap {
		$storedHostSubject = $pageSubjects->getAllSubjects()->getSubject( $hostSubjectId );

		if ( $storedHostSubject === null ) {
			return new SubjectMap();
		}

		return $this->dependentSubjectRules->dependentSubjectsReachedFrom( $storedHostSubject, $pageSubjects );
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private function buildSubject( mixed $item ): Subject {
		if ( !is_array( $item ) || !is_string( $item['id'] ?? null ) || !is_string( $item['schema'] ?? null )
			|| !is_array( $item['statements'] ?? null ) || !is_string( $item['label'] ?? '' ) ) {
			throw new InvalidArgumentException(
				'Each item of dependentSubjects must be an object with a string id, a string schema and statements'
			);
		}

		$schemaReference = $this->schemaReferenceParser->localName( $item['schema'] );
		$schema = $this->schemaResolver->getSchema( $schemaReference );

		if ( $schema === null || !$schema->subjectsAreDependent() ) {
			throw new InvalidArgumentException(
				"dependentSubjects holds a Subject of the Schema {$schemaReference->getText()}, which does not declare its Subjects dependent"
			);
		}

		// Kept, not dropped, so that the rule against labelled Dependent Subjects refuses it.
		return new Subject(
			id: $this->newSubjectIdResolver->resolve( $item['id'] ),
			label: SubjectLabel::fromText( $item['label'] ?? null ),
			schema: $schemaReference,
			statements: $this->statementListBuilder->build(
				$this->selectStatementResolver->resolve( $schema, $item['statements'] )
			),
		);
	}

	private function takesTheIdOfAnotherSubject( Subject $subject, PageSubjects $pageSubjects, SubjectMap $replaceableSubjects ): bool {
		$replaceableSubject = $replaceableSubjects->getSubject( $subject->id );

		if ( $replaceableSubject !== null ) {
			return !$replaceableSubject->getSchemaReference()->equals( $subject->getSchemaReference() );
		}

		return $pageSubjects->getAllSubjects()->hasSubject( $subject->id )
			|| $this->newSubjectIdResolver->isInUse( $subject->id );
	}

}
