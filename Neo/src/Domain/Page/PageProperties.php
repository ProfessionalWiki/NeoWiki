<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

class PageProperties {

	public function __construct(
		public readonly string $title
	) {
	}

}
