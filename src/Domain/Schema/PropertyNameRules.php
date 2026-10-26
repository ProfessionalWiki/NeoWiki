<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;
use Normalizer;

/**
 * What a Property name must be to be written. Reading never applies these rules, and a name's exact spelling stays
 * its identity. The frontend's propertyNameRules.ts answers tests/vectors/property-names.json alike.
 */
class PropertyNameRules {

	public const int MAX_BYTES = 255;

	/**
	 * Unicode's White_Space property. Not PCRE's \s, which also matches U+180E.
	 */
	private const string WHITE_SPACE_RUNS = '/[\x{0009}-\x{000D}\x{0020}\x{0085}\x{00A0}\x{1680}\x{2000}-\x{200A}'
		. '\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+/u';

	/**
	 * Formatting characters and fillers that no script needs, so a canonical name holds none.
	 */
	private const string INVISIBLE_CHARACTERS = '/[\x{00AD}\x{061C}\x{115F}\x{1160}\x{200B}\x{200E}\x{200F}'
		. '\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{206F}\x{3164}\x{FEFF}\x{FFA0}\x{FFF9}-\x{FFFB}'
		. '\x{1D173}-\x{1D17A}\x{E0001}]/u';

	/**
	 * Unicode's Default_Ignorable_Code_Point property.
	 */
	private const string DEFAULT_IGNORABLE_CODE_POINTS = '/[\x{00AD}\x{034F}\x{061C}\x{115F}\x{1160}\x{17B4}\x{17B5}'
		. '\x{180B}-\x{180F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{3164}\x{FE00}-\x{FE0F}\x{FEFF}'
		. '\x{FFA0}\x{FFF0}-\x{FFF8}\x{1BCA0}-\x{1BCA3}\x{1D173}-\x{1D17A}\x{E0000}-\x{E0FFF}]/u';

	private const array TITLE_CHARACTERS = [ '#', '<', '>', '[', ']', '|', '{', '}' ];

	private const array RESERVED_NAMES = [ 'id', 'name', 'wiki_id', '__proto__' ];

	/**
	 * @throws InvalidArgumentException When the name breaks a rule, saying which.
	 */
	public static function assertValid( string $name ): void {
		$problem = self::problemWith( $name );

		if ( $problem !== null ) {
			throw new InvalidArgumentException( $problem->explanation() );
		}
	}

	/**
	 * The first rule the name breaks, the near-duplicate rule aside, or null.
	 */
	public static function problemWith( string $name ): ?PropertyNameProblem {
		// Before normalizing, which takes time quadratic in a long run of combining marks.
		if ( strlen( $name ) > self::MAX_BYTES ) {
			return new PropertyNameProblem( $name, PropertyNameRule::Length );
		}

		$canonicalForm = self::canonicalForm( $name );
		$characters = self::forbiddenCharacters( $canonicalForm );

		if ( $characters !== [] ) {
			return new PropertyNameProblem( $name, PropertyNameRule::Characters, $characters );
		}

		$rule = self::ruleBrokenBy( $canonicalForm );

		if ( $rule !== null ) {
			return new PropertyNameProblem( $name, $rule );
		}

		if ( $canonicalForm !== $name ) {
			return new PropertyNameProblem(
				$name,
				PropertyNameRule::CanonicalForm,
				[ $canonicalForm, ...self::hiddenDifference( $name, $canonicalForm ) ]
			);
		}

		return null;
	}

	private static function canonicalForm( string $name ): string {
		return (string)Normalizer::normalize(
			self::collapseWhiteSpace( (string)preg_replace( self::INVISIBLE_CHARACTERS, '', $name ) ),
			Normalizer::NFC
		);
	}

	private static function collapseWhiteSpace( string $text ): string {
		return trim( (string)preg_replace( self::WHITE_SPACE_RUNS, ' ', $text ), ' ' );
	}

	/**
	 * @return string[]
	 */
	private static function forbiddenCharacters( string $name ): array {
		$characters = [];

		foreach ( mb_str_split( $name ) as $character ) {
			if ( in_array( $character, self::TITLE_CHARACTERS, true ) ) {
				$characters[] = $character;
			} elseif ( self::isControlOrNoncharacter( mb_ord( $character ) ) ) {
				$characters[] = self::codePoint( $character );
			}
		}

		return array_values( array_unique( $characters ) );
	}

	private static function isControlOrNoncharacter( int $codePoint ): bool {
		return $codePoint <= 0x1F
			|| ( $codePoint >= 0x7F && $codePoint <= 0x9F )
			|| ( $codePoint >= 0xFDD0 && $codePoint <= 0xFDEF )
			|| $codePoint % 0x10000 >= 0xFFFE
			|| $codePoint === 0xFFFD;
	}

	private static function codePoint( string $character ): string {
		return sprintf( 'U+%04X', mb_ord( $character ) );
	}

	private static function ruleBrokenBy( string $canonicalForm ): ?PropertyNameRule {
		return match ( true ) {
			$canonicalForm === '' => PropertyNameRule::Empty,
			preg_match( '/^-?[0-9]+$/D', $canonicalForm ) === 1 => PropertyNameRule::Integer,
			$canonicalForm === '.' || $canonicalForm === '..' => PropertyNameRule::DotSegment,
			strlen( $canonicalForm ) > self::MAX_BYTES => PropertyNameRule::Length,
			in_array( $canonicalForm, self::RESERVED_NAMES, true ) => PropertyNameRule::Reserved,
			default => null,
		};
	}

	/**
	 * Nothing when only spaces differ. Otherwise the characters outside printable ASCII that the canonical form lacks,
	 * or all of them when it lacks none, as when combining marks are only reordered.
	 *
	 * @return string[]
	 */
	private static function hiddenDifference( string $name, string $canonicalForm ): array {
		if ( trim( (string)preg_replace( '/ +/', ' ', $name ), ' ' ) === $canonicalForm ) {
			return [];
		}

		$hiddenCharacters = array_filter(
			mb_str_split( $name ),
			static fn ( string $character ): bool => preg_match( '/^[\x20-\x7E]$/', $character ) !== 1
		);
		$lackedCharacters = array_diff( $hiddenCharacters, mb_str_split( $canonicalForm ) );

		return array_values( array_unique( array_map(
			self::codePoint( ... ),
			$lackedCharacters === [] ? $hiddenCharacters : $lackedCharacters
		) ) );
	}

	/**
	 * Every problem with the Property names of one Schema: the first rule each name breaks, or else whether it is a
	 * near-duplicate of an earlier name.
	 *
	 * @param string[] $names
	 * @return PropertyNameProblem[]
	 */
	public static function problemsInSchema( array $names ): array {
		$names = array_values( $names );
		$keys = array_map( self::nearDuplicateKey( ... ), $names );
		$indicesByKey = self::indicesByKey( $keys );

		return array_values( array_filter( array_map(
			static fn ( int $index ): ?PropertyNameProblem => self::problemInSchema(
				$names,
				$keys[$index] === null ? [] : $indicesByKey[$keys[$index]],
				$index
			),
			array_keys( $names )
		) ) );
	}

	/**
	 * @param array<int, ?string> $keys
	 * @return array<array-key, int[]> The indices of the names with each key, in order
	 */
	private static function indicesByKey( array $keys ): array {
		$indicesByKey = [];

		foreach ( $keys as $index => $key ) {
			if ( $key !== null ) {
				$indicesByKey[$key][] = $index;
			}
		}

		return $indicesByKey;
	}

	/**
	 * Null for a name too long to compare, which is never normalized.
	 */
	private static function nearDuplicateKey( string $name ): ?string {
		if ( strlen( $name ) > self::MAX_BYTES ) {
			return null;
		}

		$key = mb_strtolower( (string)Normalizer::normalize( self::canonicalForm( $name ), Normalizer::NFKC ), 'UTF-8' );
		// Lowercasing writes Σ as ς or σ by the letters around it, which PHP and browsers judge by different Unicode
		// versions.
		$key = str_replace( "\u{03C2}", "\u{03C3}", $key );
		$key = (string)preg_replace( self::DEFAULT_IGNORABLE_CODE_POINTS, '', $key );

		return (string)Normalizer::normalize(
			self::collapseWhiteSpace( str_replace( '_', ' ', $key ) ),
			Normalizer::NFKC
		);
	}

	/**
	 * A name to respell is a near-duplicate of a later name too, so that no problem advises a spelling another name
	 * has.
	 *
	 * @param string[] $names
	 * @param int[] $sameKeyIndices The indices of the names with this name's near-duplicate key, in order
	 */
	private static function problemInSchema( array $names, array $sameKeyIndices, int $index ): ?PropertyNameProblem {
		$problem = self::problemWith( $names[$index] );

		if ( $problem !== null && $problem->rule !== PropertyNameRule::CanonicalForm ) {
			return $problem;
		}

		foreach ( $sameKeyIndices as $otherIndex ) {
			if ( $otherIndex < $index || ( $problem !== null && $otherIndex > $index ) ) {
				return new PropertyNameProblem( $names[$index], PropertyNameRule::NearDuplicate, [ $names[$otherIndex] ] );
			}
		}

		return $problem;
	}

}
