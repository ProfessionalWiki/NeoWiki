<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;

class NeoWikiValueParserFunction {

	private const string DEFAULT_SEPARATOR = ', ';

	public function __construct(
		private readonly SubjectContentRepository $subjectContentRepository,
		private readonly SubjectLookup $subjectLookup,
	) {
	}

	public function handle( Parser $parser, string ...$args ): string {
		$propertyName = trim( $args[0] ?? '' );

		if ( $propertyName === '' ) {
			return '';
		}

		$params = self::parseNamedParams( array_slice( $args, 1 ) );

		$subject = $this->resolveSubject( $parser, $params );

		if ( $subject === null ) {
			return '';
		}

		$statement = $subject->getStatements()->getStatement( new PropertyName( $propertyName ) );

		if ( $statement === null ) {
			return '';
		}

		$separator = $params['separator'] ?? self::DEFAULT_SEPARATOR;

		return $this->formatValue( $statement->getValue(), $separator );
	}

	/**
	 * @param string[] $args
	 * @return array<string, string>
	 */
	private static function parseNamedParams( array $args ): array {
		$params = [];

		foreach ( $args as $arg ) {
			$parts = explode( '=', $arg, 2 );
			if ( count( $parts ) === 2 ) {
				$params[trim( $parts[0] )] = trim( $parts[1] );
			}
		}

		return $params;
	}

	/**
	 * @param array<string, string> $params
	 */
	private function resolveSubject( Parser $parser, array $params ): ?Subject {
		if ( isset( $params['subject'] ) ) {
			return $this->resolveSubjectById( $params['subject'] );
		}

		if ( isset( $params['page'] ) ) {
			return $this->resolveMainSubjectByPageName( $params['page'] );
		}

		return $this->resolveMainSubjectFromCurrentPage( $parser );
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

		return $this->subjectContentRepository
			->getSubjectContentByPageTitle( $title )
			?->getPageSubjects()
			->getMainSubject();
	}

	private function resolveMainSubjectFromCurrentPage( Parser $parser ): ?Subject {
		$title = $parser->getTitle();

		if ( $title === null ) {
			return null;
		}

		return $this->subjectContentRepository
			->getSubjectContentByPageTitle( $title )
			?->getPageSubjects()
			->getMainSubject();
	}

	private function formatValue( NeoValue $value, string $separator ): string {
		if ( $value->isEmpty() ) {
			return '';
		}

		return match ( $value->getType() ) {
			ValueType::String => implode( $separator, $value->toScalars() ),
			ValueType::Number => (string)$value->toScalars(),
			ValueType::Boolean => $value->toScalars() ? 'true' : 'false',
			ValueType::Relation => $this->formatRelationValue( $value, $separator ),
		};
	}

	private function formatRelationValue( RelationValue $value, string $separator ): string {
		$labels = array_map(
			fn( Relation $relation ) => $this->resolveRelationLabel( $relation ),
			$value->relations
		);

		return implode( $separator, $labels );
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
