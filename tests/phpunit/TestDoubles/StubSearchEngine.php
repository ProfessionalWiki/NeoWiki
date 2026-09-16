<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use DummySearchIndexFieldDefinition;
use SearchEngine;
use SearchIndexField;

/**
 * A search engine that supports extension-defined index fields, as CirrusSearch does. Core's
 * database engines do not; {@see \SearchEngineDummy} stands in for those.
 */
class StubSearchEngine extends SearchEngine {

	public function makeSearchFieldMapping( $name, $type ): SearchIndexField {
		return new DummySearchIndexFieldDefinition( $name, $type );
	}

}
