<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\EditNotice;

class SubjectEditNoticeProviderRegistry {

	/**
	 * @var SubjectEditNoticeProvider[]
	 */
	private array $providers = [];

	public function addProvider( SubjectEditNoticeProvider $provider ): void {
		$this->providers[] = $provider;
	}

	/**
	 * Registration order is presentation order, so notices registered first are shown first.
	 *
	 * @return SubjectEditNoticeProvider[]
	 */
	public function getProviders(): array {
		return $this->providers;
	}

}
