<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

final class ViolationSerializer {

	/**
	 * @return array<string, mixed>
	 */
	public static function serialize( Violation $violation ): array {
		$serialized = [
			'propertyName' => $violation->propertyName?->__toString(),
			'code' => $violation->code,
			'args' => $violation->args,
		];

		if ( $violation->valuePartIndex !== null ) {
			$serialized['valuePartIndex'] = $violation->valuePartIndex;
		}

		return $serialized;
	}

	/**
	 * @param Violation[] $violations
	 * @return array<int, array<string, mixed>>
	 */
	public static function serializeMany( array $violations ): array {
		return array_map( [ self::class, 'serialize' ], $violations );
	}

}
