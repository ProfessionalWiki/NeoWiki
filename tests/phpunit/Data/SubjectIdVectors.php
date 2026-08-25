<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

/**
 * The Subject id vectors in tests/vectors/subject-ids.json, which the TypeScript suite reads too, so
 * that the two id grammars are asserted equal rather than assumed so. The canonicalization vectors are
 * this side's alone: they need the local Source key, which only the write path has.
 */
class SubjectIdVectors {

	public const string PATH = __DIR__ . '/../../vectors/subject-ids.json';

	public static function localSourceKey(): string {
		return self::read()['localSourceKey'];
	}

	/**
	 * @return array<int, array{case: string, input: string, text: string, source: ?string, localId: string}>
	 */
	public static function valid(): array {
		return array_merge( self::read()['valid'], self::read()['canonicalization']['valid'] );
	}

	/**
	 * @return array<int, array{case: string, input: string}>
	 */
	public static function invalid(): array {
		return array_merge( self::read()['invalid'], self::read()['canonicalization']['invalid'] );
	}

	private static function read(): array {
		return json_decode( (string)file_get_contents( self::PATH ), true );
	}

}
