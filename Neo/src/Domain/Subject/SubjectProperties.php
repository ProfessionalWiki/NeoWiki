<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

class SubjectProperties {

	public function __construct(
		/**
		 * @var array<string, mixed[]>
		 */
		public readonly array $map,
	) {
	}

	/**
	 * @param array<string, array> $patch Property name to list of new values
	 */
	public function applyPatch( array $patch ): self {
		$newMap = $this->map;

		foreach ( $patch as $propertyName => $values ) {
			$newMap[$propertyName] = $values;
		}

		return new self( $newMap );
	}

}
