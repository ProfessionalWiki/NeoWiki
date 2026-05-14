<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use InvalidArgumentException;
use ReflectionClass;

readonly class SubjectLabel {

	public string $text;

	/**
	 * @throws InvalidArgumentException when text is empty or whitespace.
	 *
	 * For validate-endpoint paths that need to accept empty labels and report
	 * `label-required` as a Violation, use `createForValidation()` instead.
	 */
	public function __construct( string $text ) {
		if ( trim( $text ) === '' ) {
			throw new InvalidArgumentException( 'SubjectLabel cannot be empty' );
		}
		$this->text = $text;
	}

	/**
	 * Construct without rejecting empty/whitespace text. Use only on the
	 * validate-without-commit path, where empty labels are surfaced as
	 * Violations rather than exceptions.
	 */
	public static function createForValidation( string $text ): self {
		$instance = ( new ReflectionClass( self::class ) )->newInstanceWithoutConstructor();
		$property = ( new ReflectionClass( self::class ) )->getProperty( 'text' );
		$property->setValue( $instance, $text );
		return $instance;
	}

}
