<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

class PageProperties {

	/**
	 * @param string $creationTime In the standard MediaWiki format, ie 20230726163439
	 * @param string $modificationTime In the standard MediaWiki format, ie 20230726163439
	 * @param string[] $categories
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $creationTime,
		public readonly string $modificationTime,
		public array $categories,
		public readonly string $lastEditor
	) {
	}

}
