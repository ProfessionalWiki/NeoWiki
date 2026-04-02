<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\BooleanValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;

class SubjectDataLookup {

	public function __construct(
		private readonly SubjectContentRepository $subjectContentRepository,
		private readonly SubjectLookup $subjectLookup,
	) {
	}

	/**
	 * @return mixed[] Single-element array for Lua: [value] or [null]
	 */
	public function getValue( Title $currentTitle, string $propertyName, ?array $options = null ): array {
		$propertyName = trim( $propertyName );

		if ( $propertyName === '' ) {
			return [ null ];
		}

		$subject = $this->resolveSubjectFromOptions( $currentTitle, $options );

		if ( $subject === null ) {
			return [ null ];
		}

		$statement = $subject->getStatements()->getStatement( new PropertyName( $propertyName ) );

		if ( $statement === null ) {
			return [ null ];
		}

		return [ $this->convertValueForLua( $statement->getValue() ) ];
	}

	private function resolveSubjectFromOptions( Title $currentTitle, ?array $options ): ?Subject {
		if ( $options !== null && isset( $options['subject'] ) ) {
			return $this->resolveSubjectById( (string)$options['subject'] );
		}

		if ( $options !== null && isset( $options['page'] ) ) {
			return $this->resolveMainSubjectByPageName( (string)$options['page'] );
		}

		return $this->resolveMainSubjectByTitle( $currentTitle );
	}

	private function resolveSubjectById( string $subjectIdText ): ?Subject {
		if ( !SubjectId::isValid( $subjectIdText ) ) {
			return null;
		}

		try {
			return $this->subjectLookup->getSubject( new SubjectId( $subjectIdText ) );
		} catch ( \Exception ) {
			return null;
		}
	}

	private function resolveMainSubjectByPageName( string $pageName ): ?Subject {
		$title = Title::newFromText( $pageName );

		if ( $title === null ) {
			return null;
		}

		return $this->resolveMainSubjectByTitle( $title );
	}

	private function resolveMainSubjectByTitle( Title $title ): ?Subject {
		return $this->subjectContentRepository
			->getSubjectContentByPageTitle( $title )
			?->getPageSubjects()
			->getMainSubject();
	}

	/**
	 * @return mixed Scalar, 1-indexed array, or null
	 */
	private function convertValueForLua( NeoValue $value ): mixed {
		if ( $value->isEmpty() ) {
			return null;
		}

		if ( $value instanceof StringValue ) {
			return $this->convertStringForLua( $value->strings );
		}

		if ( $value instanceof NumberValue ) {
			return $value->number;
		}

		if ( $value instanceof BooleanValue ) {
			return $value->boolean;
		}

		if ( $value instanceof RelationValue ) {
			return $this->convertRelationsForLua( $value );
		}

		return null;
	}

	/**
	 * @param string[] $strings
	 * @return string|array<int, string>
	 */
	private function convertStringForLua( array $strings ): string|array {
		if ( count( $strings ) === 1 ) {
			return $strings[0];
		}

		return array_combine(
			range( 1, count( $strings ) ),
			array_values( $strings )
		);
	}

	/**
	 * @return string|array<int, string>
	 */
	private function convertRelationsForLua( RelationValue $value ): string|array {
		$labels = array_map(
			fn( Relation $relation ) => $this->resolveRelationLabel( $relation ),
			$value->relations
		);

		if ( count( $labels ) === 1 ) {
			return $labels[0];
		}

		return array_combine(
			range( 1, count( $labels ) ),
			array_values( $labels )
		);
	}

	private function resolveRelationLabel( Relation $relation ): string {
		try {
			$subject = $this->subjectLookup->getSubject( $relation->targetId );

			if ( $subject !== null ) {
				return $subject->getLabel()->text;
			}
		} catch ( \Exception ) {
			// Fall through to ID
		}

		return $relation->targetId->text;
	}

}
