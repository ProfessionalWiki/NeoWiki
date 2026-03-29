<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

class PagePropertyProviderRegistry {

	/**
	 * @var PagePropertyProvider[]
	 */
	private array $providers = [];

	public function addProvider( PagePropertyProvider $provider ): void {
		$this->providers[] = $provider;
	}

	/**
	 * @return PagePropertyProvider[]
	 */
	public function getProviders(): array {
		return $this->providers;
	}

}
