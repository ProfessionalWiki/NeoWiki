<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use Exception;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;

/**
 * Fails for one page and contributes nothing for the rest, as a provider owning state outside the
 * wiki does when that state is unreachable for a particular page. Which exception it throws is the
 * test's choice, since the paths building page properties treat an infrastructure failure
 * (TimeoutException, DBError) differently from a provider that simply could not answer.
 */
class ThrowingPagePropertyProvider implements PagePropertyProvider {

	public function __construct(
		private readonly string $failingPageTitle,
		private readonly Exception $failure,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getProperties( PagePropertyProviderContext $context ): array {
		if ( $context->pageTitle === $this->failingPageTitle ) {
			throw $this->failure;
		}

		return [];
	}

}
