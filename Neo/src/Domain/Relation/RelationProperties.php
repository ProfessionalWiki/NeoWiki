<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

class RelationProperties {

	public function __construct(
		/**
		 * @var array<string, string|int>
		 */
		public readonly array $map,
	) {
	}

}
