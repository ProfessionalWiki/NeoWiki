<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

/**
 * Builds {@see Literal}s with the correct xsd datatype for the native projection. Centralizes the
 * numeric lexical rules (fractionless floats project as xsd:integer; decimals avoid scientific
 * notation).
 */
class RdfLiteralFactory {

	public static function typed( string $lexicalForm, string $xsdLocalName ): Literal {
		return new Literal( $lexicalForm, new Iri( RdfNamespaces::XSD . $xsdLocalName ) );
	}

	public static function languageTagged( string $lexicalForm, string $languageTag ): Literal {
		return new Literal( $lexicalForm, new Iri( RdfNamespaces::RDF . 'langString' ), $languageTag );
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

	private static function decimalLexical( float $number ): string {
		$formatted = rtrim( sprintf( '%.15F', $number ), '0' );

		return str_ends_with( $formatted, '.' ) ? $formatted . '0' : $formatted;
	}

}
