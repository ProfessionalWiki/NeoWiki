<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

/**
 * Value whose Property Type is not registered, because the extension owning the type is
 * disabled or failed to load. The stored JSON is kept as decoded and re-serialized
 * unchanged, so the Statement survives a save and the data is intact once the type is
 * registered again.
 *
 * The one shape that does not survive is a JSON object that PHP cannot tell from a list
 * once decoded: `{}` and `{"0": ...}` re-serialize as `[]` and `[...]`. No Property Type
 * can store one, since every Value Type is a string, number, boolean, or relation list,
 * so this is reachable only by hand-editing the stored JSON.
 *
 * See ADR 12 and ADR 21: invalid Subjects are a supported state, and invalid parts must
 * not disappear on save.
 */
readonly class UnregisteredTypeValue implements NeoValue {

	public function __construct(
		public string $propertyType,
		public mixed $rawValue,
	) {
	}

	public function getType(): ValueType {
		return ValueType::UnregisteredType;
	}

	public function toScalars(): mixed {
		return $this->rawValue;
	}

	/**
	 * Never empty: an empty Value is dropped on save (@see StatementListBuilder::build),
	 * which is exactly the data loss this Value exists to prevent.
	 */
	public function isEmpty(): bool {
		return false;
	}

}
