<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\Application\SubjectResolver;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Value\BooleanValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;

class NeoWikiValueParserFunction {

	private const DEFAULT_SEPARATOR = ', ';

	public function __construct(
		private readonly SubjectResolver $subjectResolver,
	) {
	}

	/**
	 * @return array{0: string, noparse: true, isHTML: true}|string
	 */
	public function handle( Parser $parser, string ...$args ): string|array {
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
		$formatted = $this->formatValue( $statement->getValue(), $separator );

		if ( $formatted === '' ) {
			return '';
		}

		return [ htmlspecialchars( $formatted ), 'noparse' => true, 'isHTML' => true ];
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
			return $this->subjectResolver->resolveById( $params['subject'] );
		}

		if ( isset( $params['page'] ) ) {
			return $this->subjectResolver->resolveMainByPageName( $params['page'] );
		}

		$title = $parser->getTitle();

		if ( $title === null ) {
			return null;
		}

		return $this->subjectResolver->resolveMainByTitle( $title );
	}

	private function formatValue( NeoValue $value, string $separator ): string {
		if ( $value->isEmpty() ) {
			return '';
		}

		if ( $value instanceof StringValue ) {
			return implode( $separator, $value->strings );
		}

		if ( $value instanceof NumberValue ) {
			return (string)$value->number;
		}

		if ( $value instanceof BooleanValue ) {
			return $value->boolean ? 'true' : 'false';
		}

		if ( $value instanceof RelationValue ) {
			return $this->formatRelationValue( $value, $separator );
		}

		return '';
	}

	private function formatRelationValue( RelationValue $value, string $separator ): string {
		$labels = array_map(
			fn( Relation $relation ) => $this->subjectResolver->resolveRelationLabel( $relation ),
			$value->relations
		);

		return implode( $separator, $labels );
	}

}
