<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;

class RelationValue implements NeoValue {

	/**
	 * @var Relation[]
	 */
	public readonly array $relations;

	public function __construct( Relation ...$relations ) {
		$this->relations = $relations;
	}

	public function getType(): ValueType {
		return ValueType::Relation;
	}

	public function toScalars(): array {
		return array_map(
			static function ( Relation $relation ): array {
				$array = [
					'id' => $relation->id->asString(),
					'target' => $relation->targetId->text,
				];

				if ( $relation->hasProperties() ) {
					$array['properties'] = $relation->properties->map;
				}

				return $array;
			},
			$this->relations
		);
	}

	public function isEmpty(): bool {
		return $this->relations === [];
	}

}
