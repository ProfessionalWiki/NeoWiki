<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Linker\LinkTarget;

class MappingPageRendering {

	/**
	 * @param list<LinkTarget> $schemaLinks Schema pages the mapping references, for the ParserOutput link table.
	 * @param list<string> $externalLinks Prefix namespace IRIs, for the ParserOutput external link table.
	 */
	public function __construct(
		public readonly string $html,
		public readonly array $schemaLinks,
		public readonly array $externalLinks
	) {
	}

}
