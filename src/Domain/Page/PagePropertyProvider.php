<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

interface PagePropertyProvider {

	/**
	 * @return array<string, mixed>
	 */
	public function getProperties( PagePropertyProviderContext $context ): array;

}
