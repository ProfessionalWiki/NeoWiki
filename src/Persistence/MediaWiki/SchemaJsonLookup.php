<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\Schema\Exception\SchemaContentUnavailableException;

/**
 * The stored JSON of a Schema page, before it is parsed into a Schema.
 */
interface SchemaJsonLookup {

	/**
	 * @throws SchemaContentUnavailableException When the page's content could not be read.
	 */
	public function getSchemaJson( Title $schemaPage ): string;

}
