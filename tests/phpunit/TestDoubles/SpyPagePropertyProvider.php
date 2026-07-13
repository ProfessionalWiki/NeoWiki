<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;

class SpyPagePropertyProvider implements PagePropertyProvider {

	private ?PagePropertyProviderContext $receivedContext = null;

	public function getProperties( PagePropertyProviderContext $context ): array {
		$this->receivedContext = $context;
		return [];
	}

	public function getReceivedContext(): PagePropertyProviderContext {
		if ( $this->receivedContext === null ) {
			throw new \LogicException( 'getProperties was not called' );
		}

		return $this->receivedContext;
	}

}
