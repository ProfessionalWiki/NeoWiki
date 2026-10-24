<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

/**
 * The Property name vectors in tests/vectors/property-names.json, which the TypeScript suite reads too, so that the
 * Schema editor and the wiki are asserted to refuse the same names rather than assumed to.
 */
class PropertyNameVectors {

	public const string PATH = __DIR__ . '/../../vectors/property-names.json';

	/**
	 * @return array<int, array{case: string, name: string}>
	 */
	public static function valid(): array {
		return self::read()['valid'];
	}

	/**
	 * @return array<int, array{case: string, name: string, problem: array{rule: string, details: string[]}}>
	 */
	public static function invalid(): array {
		return self::read()['invalid'];
	}

	/**
	 * @return array<int, array{case: string, names: string[], problems: array<int, array{name: string, rule: string, details: string[]}>}>
	 */
	public static function schemas(): array {
		return self::read()['schemas'];
	}

	private static function read(): array {
		return json_decode( (string)file_get_contents( self::PATH ), true );
	}

}
