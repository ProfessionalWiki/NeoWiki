<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

class PageId {

	public function __construct(
		public readonly int $id
	) {
	}

}
