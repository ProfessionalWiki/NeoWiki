<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

readonly class CompositeCypherQueryValidator implements CypherQueryValidator {

	/**
	 * @param CypherQueryValidator[] $validators
	 */
	public function __construct(
		private array $validators
	) {
	}

	public function queryIsAllowed( string $cypher ): bool {
		foreach ( $this->validators as $validator ) {
			if ( !$validator->queryIsAllowed( $cypher ) ) {
				return false;
			}
		}

		return true;
	}

}
