<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

readonly class PageId {

	public function __construct(
		public int $id
	) {
	}

}
