<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

/**
 * Marker interface for page property values that need backend-specific
 * type handling (e.g., Neo4j datetime, point, duration).
 *
 * Plain scalars can be used directly in PagePropertyProvider return arrays.
 * Use a PageTypedValue implementation when the value needs to be stored
 * using a backend's native type system.
 */
interface PageTypedValue {

	public function getType(): string;

	public function getValue(): mixed;

}
