<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

class RelationList {

	public function __construct(
		/**
		 * @var Relation[]
		 */
		public readonly array $relations
	) {
	}

	/**
	 * @return array<string, array<int, array<string, string>>>
	 */
	public function asMap(): array {
		$map = [];

		foreach ( $this->relations as $relation ) {
			// TODO: at this point we don't know if it's a single relation or an array of relations.
			$map[$relation->type->text] ??= [];
			$map[$relation->type->text][] = [
				'target' => $relation->targetId->text,
				'properties' => $relation->properties->map,
			];
		}

		return $map;
	}

}
