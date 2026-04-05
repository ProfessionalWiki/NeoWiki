<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Scribunto;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\SubjectResolver;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Value\BooleanValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;

class SubjectDataLookup {

	public function __construct(
		private readonly SubjectResolver $subjectResolver,
	) {
	}

	/**
	 * Returns a single scalar value for the property (first value for multi-valued).
	 * Relations resolve to the target subject's label.
	 *
	 * @return mixed[] Single-element array for Lua: [value] or [null]
	 */
	public function getValue( Title $currentTitle, string $propertyName, ?array $options = null ): array {
		$statement = $this->resolveStatement( $currentTitle, $propertyName, $options );

		if ( $statement === null ) {
			return [ null ];
		}

		return [ $this->convertToScalar( $statement->getValue() ) ];
	}

	/**
	 * Returns all values for the property as a 1-indexed Lua table.
	 * Relations resolve to the target subject's label.
	 *
	 * @return mixed[] Single-element array for Lua: [table] or [null]
	 */
	public function getAll( Title $currentTitle, string $propertyName, ?array $options = null ): array {
		$statement = $this->resolveStatement( $currentTitle, $propertyName, $options );

		if ( $statement === null ) {
			return [ null ];
		}

		return [ $this->convertToLuaTable( $statement->getValue() ) ];
	}

	private function resolveStatement( Title $currentTitle, string $propertyName, ?array $options ): ?Statement {
		$propertyName = trim( $propertyName );

		if ( $propertyName === '' ) {
			return null;
		}

		$subject = $this->resolveSubjectFromOptions( $currentTitle, $options );

		if ( $subject === null ) {
			return null;
		}

		return $subject->getStatements()->getStatement( new PropertyName( $propertyName ) );
	}

	private function resolveSubjectFromOptions( Title $currentTitle, ?array $options ): ?Subject {
		if ( $options !== null && isset( $options['subject'] ) ) {
			return $this->subjectResolver->resolveById( (string)$options['subject'] );
		}

		if ( $options !== null && isset( $options['page'] ) ) {
			return $this->subjectResolver->resolveMainByPageName( (string)$options['page'] );
		}

		return $this->subjectResolver->resolveMainByTitle( $currentTitle );
	}

	/**
	 * Returns the first/only value as a scalar. Null for empty values.
	 */
	private function convertToScalar( NeoValue $value ): mixed {
		if ( $value->isEmpty() ) {
			return null;
		}

		if ( $value instanceof StringValue ) {
			return $value->strings[0];
		}

		if ( $value instanceof NumberValue ) {
			return $value->number;
		}

		if ( $value instanceof BooleanValue ) {
			return $value->boolean;
		}

		if ( $value instanceof RelationValue ) {
			return $this->subjectResolver->resolveRelationLabel( $value->relations[0] );
		}

		return null;
	}

	/**
	 * Returns all values as a 1-indexed Lua table. Null for empty values.
	 *
	 * @return array<int, mixed>|null
	 */
	private function convertToLuaTable( NeoValue $value ): ?array {
		if ( $value->isEmpty() ) {
			return null;
		}

		if ( $value instanceof StringValue ) {
			return $this->toLuaIndexed( $value->strings );
		}

		if ( $value instanceof NumberValue ) {
			return [ 1 => $value->number ];
		}

		if ( $value instanceof BooleanValue ) {
			return [ 1 => $value->boolean ];
		}

		if ( $value instanceof RelationValue ) {
			return $this->relationLabelsToLuaTable( $value );
		}

		return null;
	}

	/**
	 * @return array{0: ?array<string, mixed>}
	 */
	public function getMainSubjectData( Title $currentTitle, ?string $pageName = null ): array {
		$title = $this->resolveTitle( $currentTitle, $pageName );

		if ( $title === null ) {
			return [ null ];
		}

		$pageSubjects = $this->subjectResolver->getPageSubjectsByTitle( $title );

		if ( $pageSubjects === null ) {
			return [ null ];
		}

		$subject = $pageSubjects->getMainSubject();

		if ( $subject === null ) {
			return [ null ];
		}

		return [ $this->subjectToTable( $subject ) ];
	}

	/**
	 * @return array{0: ?array<string, mixed>}
	 */
	public function getSubjectData( string $subjectId ): array {
		$subject = $this->subjectResolver->resolveById( $subjectId );

		if ( $subject === null ) {
			return [ null ];
		}

		return [ $this->subjectToTable( $subject ) ];
	}

	/**
	 * @return array{0: array<int, array<string, mixed>>}
	 */
	public function getChildSubjectsData( Title $currentTitle, ?string $pageName = null ): array {
		$title = $this->resolveTitle( $currentTitle, $pageName );

		if ( $title === null ) {
			return [ [] ];
		}

		$pageSubjects = $this->subjectResolver->getPageSubjectsByTitle( $title );

		if ( $pageSubjects === null ) {
			return [ [] ];
		}

		$children = $pageSubjects->getChildSubjects()->asArray();

		if ( $children === [] ) {
			return [ [] ];
		}

		$result = [];
		$index = 1;
		foreach ( $children as $child ) {
			$result[$index++] = $this->subjectToTable( $child );
		}

		return [ $result ];
	}

	private function resolveTitle( Title $currentTitle, ?string $pageName ): ?Title {
		if ( $pageName !== null && $pageName !== '' ) {
			return Title::newFromText( $pageName );
		}

		return $currentTitle;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function subjectToTable( Subject $subject ): array {
		return [
			'id' => $subject->getId()->text,
			'label' => $subject->getLabel()->text,
			'schema' => $subject->getSchemaName()->getText(),
			'statements' => $this->statementsToTable( $subject ),
		];
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function statementsToTable( Subject $subject ): array {
		$result = [];

		foreach ( $subject->getStatements()->asArray() as $propertyName => $statement ) {
			$result[$propertyName] = $this->statementToTable( $statement );
		}

		return $result;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function statementToTable( Statement $statement ): array {
		return [
			'type' => $statement->getPropertyType(),
			'values' => $this->statementValuesToLuaArray( $statement ),
		];
	}

	/**
	 * @return array<int, mixed>
	 */
	private function statementValuesToLuaArray( Statement $statement ): array {
		$value = $statement->getValue();

		if ( $value->isEmpty() ) {
			return [];
		}

		if ( $value instanceof StringValue ) {
			return $this->toLuaIndexed( $value->strings );
		}

		if ( $value instanceof NumberValue ) {
			return [ 1 => $value->number ];
		}

		if ( $value instanceof BooleanValue ) {
			return [ 1 => $value->boolean ];
		}

		if ( $value instanceof RelationValue ) {
			return $this->relationsToLuaArray( $value );
		}

		return [];
	}

	/**
	 * @param array<int, mixed> $values
	 * @return array<int, mixed>
	 */
	private function toLuaIndexed( array $values ): array {
		return array_combine(
			range( 1, count( $values ) ),
			array_values( $values )
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function relationLabelsToLuaTable( RelationValue $value ): array {
		$labels = array_map(
			fn( Relation $relation ) => $this->subjectResolver->resolveRelationLabel( $relation ),
			$value->relations
		);

		return $this->toLuaIndexed( $labels );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function relationsToLuaArray( RelationValue $value ): array {
		$result = [];
		$index = 1;

		foreach ( $value->relations as $relation ) {
			$label = $this->subjectResolver->resolveRelationLabel( $relation );

			$result[$index++] = [
				'id' => $relation->id->asString(),
				'target' => $relation->targetId->text,
				'label' => $label,
			];
		}

		return $result;
	}

}
