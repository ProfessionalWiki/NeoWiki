<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

/**
 * The shape a language tag must have wherever NeoWiki stores or emits one: on a
 * {@see \ProfessionalWiki\NeoWiki\Domain\Value\MonolingualText} part, on an RDF
 * {@see \ProfessionalWiki\NeoWiki\Domain\Rdf\Literal}, and on a Mapping's `lang`.
 *
 * Shape only. Case is left to the holder: a MonolingualText part lowercases its tag, while a
 * Mapping's `lang` is emitted as the author wrote it.
 */
class LanguageTag {

	/**
	 * A BCP-47-shaped language tag: hyphen-separated subtags of 1–8 characters, the primary subtag
	 * alphabetic and the rest alphanumeric. Kept in sync with the `lang` pattern in
	 * mappingContentSchema.json (the save-time layer).
	 *
	 * Carries no delimiters and stays within ECMA-262, so that the JSON Schema documents can use it
	 * as a `pattern`.
	 */
	public const string PATTERN = '^[A-Za-z]{1,8}(-[A-Za-z0-9]{1,8})*$';

	// The `D` modifier pins `$` to the very end of the string so a trailing newline cannot slip
	// characters past the check.
	private const string REGEX = '/' . self::PATTERN . '/D';

	public static function isValid( string $tag ): bool {
		return preg_match( self::REGEX, $tag ) === 1;
	}

}
