<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;

/**
 * Contributes page properties whose values a test can change between projections, as an extension
 * owning state outside the wikitext does when that state changes.
 */
class MutablePagePropertyProvider implements PagePropertyProvider {

	/**
	 * @param array<string, mixed> $properties
	 */
	public function __construct(
		public array $properties = [],
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getProperties( PagePropertyProviderContext $context ): array {
		return $this->properties;
	}

}
