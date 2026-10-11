<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;

readonly class RelationValue implements NeoValue {

	/**
	 * @var Relation[]
	 */
	public array $relations;

	public function __construct( Relation ...$relations ) {
		$this->relations = $relations;
	}

	public function getType(): ValueType {
		return ValueType::Relation;
	}

	public function toScalars(): array {
		return array_map(
			static fn ( Relation $relation ): array => [
				'id' => $relation->id->asString(),
				'target' => $relation->targetId->text,
			],
			$this->relations
		);
	}

	public function isEmpty(): bool {
		return $this->relations === [];
	}

}
