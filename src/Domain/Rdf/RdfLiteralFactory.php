<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

/**
 * Builds {@see Literal}s with the correct xsd datatype for the native projection. Centralizes the
 * numeric lexical rules (fractionless floats project as xsd:integer; decimals avoid scientific
 * notation) so the value mappers and the relation-property projection share one implementation.
 */
class RdfLiteralFactory {

	public static function typed( string $lexicalForm, string $xsdLocalName ): Literal {
		return new Literal( $lexicalForm, new Iri( RdfNamespaces::XSD . $xsdLocalName ) );
	}

	public static function boolean( bool $value ): Literal {
		return self::typed( $value ? 'true' : 'false', 'boolean' );
	}

	public static function number( int|float $number ): ?Literal {
		if ( is_int( $number ) ) {
			return self::typed( (string)$number, 'integer' );
		}

		if ( !is_finite( $number ) ) {
			return null;
		}

		// A fractionless float is projected as xsd:integer, matching the spec's value type mapping.
		if ( $number === floor( $number ) && abs( $number ) < 1.0e15 ) {
			return self::typed( (string)(int)$number, 'integer' );
		}

		return self::typed( self::decimalLexical( $number ), 'decimal' );
	}

	/**
	 * Maps a raw scalar (as found in a Relation's property map) to a Literal, or null when it cannot
	 * be represented (e.g. null, or a non-finite float).
	 */
	public static function forScalar( mixed $value ): ?Literal {
		return match ( true ) {
			is_string( $value ) => self::typed( $value, 'string' ),
			is_bool( $value ) => self::boolean( $value ),
			is_int( $value ), is_float( $value ) => self::number( $value ),
			default => null,
		};
	}

	private static function decimalLexical( float $number ): string {
		$formatted = rtrim( sprintf( '%.15F', $number ), '0' );

		return str_ends_with( $formatted, '.' ) ? $formatted . '0' : $formatted;
	}

}
