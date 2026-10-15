<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceResolver;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;

/**
 * A Subject whose Schema does not resolve counts as standalone.
 *
 * Resolves each Schema once per instance.
 */
class DependentSubjectRules {

	public const string REFERENCED_TWICE = 'dependent-subject-referenced-twice';
	public const string TARGET_NOT_ON_PAGE = 'dependent-target-not-on-page';
	public const string RELATION_TO_DEPENDENT_SUBJECT = 'relation-to-dependent-subject';
	public const string IS_MAIN = 'dependent-subject-is-main';
	public const string HAS_LABEL = 'dependent-subject-has-label';
	public const string WITHOUT_HOST = 'dependent-subject-without-host';

	/**
	 * @var array<string, ?Schema>
	 */
	private array $schemas = [];

	public function __construct(
		private readonly SchemaReferenceResolver $schemaResolver,
	) {
	}

	public function isDependent( Subject $subject ): bool {
		return $this->declaresDependentSubjects( $subject->getSchemaReference() );
	}

	/**
	 * False for a name no Schema can have.
	 */
	public function localSchemaDeclaresDependentSubjects( string $schemaName ): bool {
		try {
			return $this->declaresDependentSubjects( SchemaReference::local( new SchemaName( $schemaName ) ) );
		} catch ( InvalidArgumentException ) {
			return false;
		}
	}

	private function declaresDependentSubjects( SchemaReference $schema ): bool {
		return $this->getSchema( $schema )?->subjectsAreDependent() === true;
	}

	/**
	 * @return array<string, Subject> Each Dependent Subject's Host Subject, by the Dependent Subject's id. Of several,
	 *   which a page breaking the rules can hold, the first in page order.
	 */
	public function hostSubjectsOn( PageSubjects $pageSubjects ): array {
		$subjects = $pageSubjects->getAllSubjects();
		$hostSubjects = [];

		foreach ( $subjects->asArray() as $candidate ) {
			foreach ( $candidate->getReferencedSubjects()->asArray() as $idText => $targetId ) {
				$target = $subjects->getSubject( $targetId );

				if ( $target !== null && !array_key_exists( $idText, $hostSubjects ) && $this->isDependent( $target ) ) {
					$hostSubjects[$idText] = $candidate;
				}
			}
		}

		return $hostSubjects;
	}

	/**
	 * The violations $after has and $before does not, so that a page already breaking a rule stays editable.
	 *
	 * @param SubjectMap $subjectsOnOtherPages The Subjects {@see self::targetsOnOtherPages()} names, as far as
	 *   they resolve.
	 * @param SubjectIdList $suppliedIds The ids in the request's `dependentSubjects`.
	 *
	 * @return Violation[]
	 */
	public function newViolations(
		PageSubjects $before,
		PageSubjects $after,
		SubjectMap $subjectsOnOtherPages,
		SubjectIdList $suppliedIds
	): array {
		return array_values( array_diff_key(
			$this->violationsOn( $after, $this->removedSubjects( $before, $after ) )
				+ $this->dependentSubjectsWithoutHost( $before, $after, $suppliedIds )
				+ $this->newRelationsToDependentSubjectsOnOtherPages( $before, $after, $subjectsOnOtherPages ),
			$this->violationsOn( $before, [] )
		) );
	}

	/**
	 * The Subjects whose Schemas {@see self::newViolations()} needs: the targets on other pages of the Relations
	 * $after adds.
	 */
	public function targetsOnOtherPages( PageSubjects $before, PageSubjects $after ): SubjectIdList {
		$targetIds = [];

		foreach ( $this->newRelationsToOtherPages( $before, $after ) as [ , , , $targetId ] ) {
			$targetIds[] = $targetId;
		}

		return new SubjectIdList( $targetIds );
	}

	public function withoutRelationsToRemovedDependentSubjects( PageSubjects $before, PageSubjects $after ): PageSubjects {
		$removedDependentSubjects = array_filter( $this->removedSubjects( $before, $after ), $this->isDependent( ... ) );

		if ( $removedDependentSubjects === [] ) {
			return $after;
		}

		return $after->withEachSubject(
			fn ( Subject $subject ): Subject => $subject->withStatements(
				$this->withoutRelationsTo( $subject->getStatements(), $removedDependentSubjects )
			)
		);
	}

	/**
	 * $after without the Dependent Subjects $before reaches and $after does not.
	 */
	public function withoutDependentSubjectsTheWriteLeavesUnreached( PageSubjects $before, PageSubjects $after ): PageSubjects {
		$reachedBefore = $this->reachedDependentSubjects( $before );
		$reachedAfter = $this->reachedDependentSubjects( $after );

		foreach ( $after->getAllSubjects()->asArray() as $subject ) {
			if ( $reachedBefore->hasSubject( $subject->id ) && !$reachedAfter->hasSubject( $subject->id ) ) {
				$after = $after->without( $subject->id );
			}
		}

		return $after;
	}

	/**
	 * The Dependent Subjects on the page that $subject points at, directly or through other Dependent
	 * Subjects, in page order.
	 */
	public function dependentSubjectsReachedFrom( Subject $subject, PageSubjects $pageSubjects ): SubjectMap {
		return $this->reachedFrom( [ $subject ], $pageSubjects->getAllSubjects() );
	}

	/**
	 * @param array<string, Subject> $removedSubjects By id. Relations to them are not checked, so that removing a
	 *   Subject is never refused.
	 *
	 * @return array<string, Violation> Keyed by what each is about, so that two states of a page compare.
	 */
	private function violationsOn( PageSubjects $pageSubjects, array $removedSubjects ): array {
		return $this->relationViolations( $pageSubjects->getAllSubjects(), $removedSubjects )
			+ $this->dependentSubjectViolations( $pageSubjects );
	}

	/**
	 * @param array<string, Subject> $removedSubjects
	 * @return array<string, Violation>
	 */
	private function relationViolations( SubjectMap $subjects, array $removedSubjects ): array {
		$violations = [];

		foreach ( $this->relationsAmong( $subjects ) as [ $host, $property, $index, $targetId ] ) {
			if ( array_key_exists( $targetId->text, $removedSubjects ) ) {
				continue;
			}

			$code = $this->relationViolationCode( $host, $property, $subjects->getSubject( $targetId ) );

			if ( $code !== null ) {
				$violations[self::key( $code, $host->id, $property, $targetId )] =
					$this->newRelationViolation( $code, $host, $property, $index, $targetId );
			}
		}

		return $violations;
	}

	/**
	 * @param ?Subject $target Null when the target is not on the page.
	 */
	private function relationViolationCode( Subject $host, PropertyName $property, ?Subject $target ): ?string {
		$targetsDependentSubjects = $this->targetsDependentSubjects( $host, $property );

		if ( $target === null ) {
			return $targetsDependentSubjects === true ? self::TARGET_NOT_ON_PAGE : null;
		}

		return $targetsDependentSubjects === false && $this->isDependent( $target ) ? self::RELATION_TO_DEPENDENT_SUBJECT : null;
	}

	/**
	 * @return array<string, Violation>
	 */
	private function dependentSubjectViolations( PageSubjects $pageSubjects ): array {
		$referenceCounts = $this->referenceCounts( $pageSubjects->getAllSubjects() );
		$violations = [];

		foreach ( $pageSubjects->getAllSubjects()->asArray() as $subject ) {
			if ( !$this->isDependent( $subject ) ) {
				continue;
			}

			$codes = array_keys( array_filter( [
				self::REFERENCED_TWICE => ( $referenceCounts[$subject->id->text] ?? 0 ) > 1,
				self::IS_MAIN => $pageSubjects->isMainSubject( $subject->id ),
				self::HAS_LABEL => $subject->getLabel() !== null,
			] ) );

			foreach ( $codes as $code ) {
				$violations[self::key( $code, $subject->id )] = $this->newSubjectViolation( $code, $subject->id );
			}
		}

		return $violations;
	}

	/**
	 * @return array<string, int> By target Subject id.
	 */
	private function referenceCounts( SubjectMap $subjects ): array {
		$counts = [];

		foreach ( $this->relationsAmong( $subjects ) as [ , , , $targetId ] ) {
			$counts[$targetId->text] = ( $counts[$targetId->text] ?? 0 ) + 1;
		}

		return $counts;
	}

	/**
	 * @return array<string, Violation>
	 */
	private function dependentSubjectsWithoutHost( PageSubjects $before, PageSubjects $after, SubjectIdList $suppliedIds ): array {
		$reached = $this->reachedDependentSubjects( $after );
		$violations = [];

		foreach ( $after->getAllSubjects()->asArray() as $subject ) {
			$isWritten = !$before->getAllSubjects()->hasSubject( $subject->id )
				|| array_key_exists( $subject->id->text, $suppliedIds->asArray() );

			if ( $isWritten && $this->isDependent( $subject ) && !$reached->hasSubject( $subject->id ) ) {
				$violations[self::key( self::WITHOUT_HOST, $subject->id )] =
					$this->newSubjectViolation( self::WITHOUT_HOST, $subject->id );
			}
		}

		return $violations;
	}

	/**
	 * @return array<string, Violation>
	 */
	private function newRelationsToDependentSubjectsOnOtherPages(
		PageSubjects $before,
		PageSubjects $after,
		SubjectMap $subjectsOnOtherPages
	): array {
		$violations = [];

		foreach ( $this->newRelationsToOtherPages( $before, $after ) as [ $host, $property, $index, $targetId ] ) {
			$target = $subjectsOnOtherPages->getSubject( $targetId );

			if ( $target !== null && $this->isDependent( $target ) ) {
				$violations[self::key( self::RELATION_TO_DEPENDENT_SUBJECT, $host->id, $property, $targetId )] =
					$this->newRelationViolation( self::RELATION_TO_DEPENDENT_SUBJECT, $host, $property, $index, $targetId );
			}
		}

		return $violations;
	}

	/**
	 * Leaves out the Relations of properties that target Dependent Subjects: {@see self::TARGET_NOT_ON_PAGE} refuses
	 * those whatever they point at.
	 *
	 * @return iterable<array{Subject, PropertyName, int, SubjectId}>
	 */
	private function newRelationsToOtherPages( PageSubjects $before, PageSubjects $after ): iterable {
		$priorRelations = $this->relationKeys( $before );
		$subjects = $after->getAllSubjects();

		foreach ( $this->relationsAmong( $subjects ) as $relation ) {
			[ $host, $property, , $targetId ] = $relation;

			if ( !$subjects->hasSubject( $targetId )
				&& !array_key_exists( self::key( '', $host->id, $property, $targetId ), $priorRelations )
				&& $this->targetsDependentSubjects( $host, $property ) !== true
			) {
				yield $relation;
			}
		}
	}

	/**
	 * @return array<string, true>
	 */
	private function relationKeys( PageSubjects $pageSubjects ): array {
		$keys = [];

		foreach ( $this->relationsAmong( $pageSubjects->getAllSubjects() ) as [ $host, $property, , $targetId ] ) {
			$keys[self::key( '', $host->id, $property, $targetId )] = true;
		}

		return $keys;
	}

	/**
	 * Null when $host's Schema does not resolve. The first target Schema that resolves decides: the target Schemas of
	 * one property all declare their Subjects dependent, or none does.
	 */
	private function targetsDependentSubjects( Subject $host, PropertyName $property ): ?bool {
		$schema = $this->getSchema( $host->getSchemaReference() );

		if ( $schema === null ) {
			return null;
		}

		$definition = $schema->hasProperty( $property ) ? $schema->getProperty( $property ) : null;

		if ( !$definition instanceof RelationProperty ) {
			return false;
		}

		foreach ( $definition->getTargetSchemas() as $targetSchema ) {
			$resolved = $this->getSchema( $targetSchema );

			if ( $resolved !== null ) {
				return $resolved->subjectsAreDependent();
			}
		}

		return false;
	}

	private function reachedDependentSubjects( PageSubjects $pageSubjects ): SubjectMap {
		$subjects = $pageSubjects->getAllSubjects();

		return $this->reachedFrom(
			array_filter( $subjects->asArray(), fn ( Subject $subject ): bool => !$this->isDependent( $subject ) ),
			$subjects
		);
	}

	/**
	 * @param Subject[] $start
	 */
	private function reachedFrom( array $start, SubjectMap $subjects ): SubjectMap {
		$reached = [];
		$toVisit = $start;

		while ( $toVisit !== [] ) {
			$subject = array_pop( $toVisit );

			foreach ( $subject->getReferencedSubjects()->asArray() as $idText => $targetId ) {
				$target = $subjects->getSubject( $targetId );

				if ( $target !== null && !array_key_exists( $idText, $reached ) && $this->isDependent( $target ) ) {
					$reached[$idText] = true;
					$toVisit[] = $target;
				}
			}
		}

		return new SubjectMap( ...array_filter(
			$subjects->asArray(),
			static fn ( Subject $subject ): bool => array_key_exists( $subject->id->text, $reached )
		) );
	}

	/**
	 * @return array<string, Subject> By id.
	 */
	private function removedSubjects( PageSubjects $before, PageSubjects $after ): array {
		$removedSubjects = [];

		foreach ( $before->getAllSubjects()->asArray() as $subject ) {
			if ( !$after->getAllSubjects()->hasSubject( $subject->id ) ) {
				$removedSubjects[$subject->id->text] = $subject;
			}
		}

		return $removedSubjects;
	}

	/**
	 * @param array<string, Subject> $targets By id.
	 */
	private function withoutRelationsTo( StatementList $statements, array $targets ): StatementList {
		foreach ( $statements->asArray() as $statement ) {
			$value = $statement->getValue();

			if ( $value instanceof RelationValue ) {
				$statements = $this->withRelations(
					$statements,
					$statement,
					array_filter(
						$value->relations,
						static fn ( Relation $relation ): bool => !array_key_exists( $relation->targetId->text, $targets )
					)
				);
			}
		}

		return $statements;
	}

	/**
	 * @param Relation[] $relations
	 */
	private function withRelations( StatementList $statements, Statement $statement, array $relations ): StatementList {
		if ( $relations === [] ) {
			return $statements->withoutStatement( $statement->getPropertyName() );
		}

		return $statements->withStatement( new Statement(
			$statement->getPropertyName(),
			$statement->getPropertyType(),
			new RelationValue( ...array_values( $relations ) )
		) );
	}

	/**
	 * @return iterable<array{Subject, PropertyName, int, SubjectId}>
	 */
	private function relationsAmong( SubjectMap $subjects ): iterable {
		foreach ( $subjects->asArray() as $subject ) {
			foreach ( $subject->getStatements()->asArray() as $statement ) {
				yield from $this->relationsOf( $subject, $statement );
			}
		}
	}

	/**
	 * @return iterable<array{Subject, PropertyName, int, SubjectId}>
	 */
	private function relationsOf( Subject $subject, Statement $statement ): iterable {
		$value = $statement->getValue();

		if ( !$value instanceof RelationValue ) {
			return;
		}

		foreach ( $value->relations as $index => $relation ) {
			yield [ $subject, $statement->getPropertyName(), (int)$index, $relation->targetId ];
		}
	}

	private function newRelationViolation(
		string $code,
		Subject $host,
		PropertyName $property,
		int $index,
		SubjectId $targetId
	): Violation {
		return new Violation(
			propertyName: $property,
			code: $code,
			args: [ $targetId->text ],
			valuePartIndex: $index,
			severity: Severity::Error,
			subjectId: $host->id,
		);
	}

	private function newSubjectViolation( string $code, SubjectId $subjectId ): Violation {
		return new Violation(
			propertyName: null,
			code: $code,
			severity: Severity::Error,
			subjectId: $subjectId,
		);
	}

	private static function key( string $code, SubjectId $subjectId, ?PropertyName $property = null, ?SubjectId $targetId = null ): string {
		return implode( "\0", [ $code, $subjectId->text, $property?->text ?? '', $targetId?->text ?? '' ] );
	}

	private function getSchema( SchemaReference $reference ): ?Schema {
		// A Source key is never empty, so the empty prefix tells a local name apart from a sourced one.
		$key = ( $reference->source ?? '' ) . "\0" . $reference->name->getText();

		if ( !array_key_exists( $key, $this->schemas ) ) {
			$this->schemas[$key] = $this->schemaResolver->getSchema( $reference );
		}

		return $this->schemas[$key];
	}

}
