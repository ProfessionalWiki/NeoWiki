<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Parser\ParserOutput;

/**
 * Records in a parse that it read the graph stores. MediaWiki renders a saved page before NeoWiki projects it,
 * so such a rendering shows the stores as they stood before the save.
 */
class GraphReads {

	private const string EXTENSION_DATA_KEY = 'neowiki-read-graph-stores';

	/**
	 * Every read counts, including one that fails: the page reads the stores again once they answer.
	 */
	public static function record( ParserOutput $output ): void {
		$output->setExtensionData( self::EXTENSION_DATA_KEY, true );
	}

	public static function happenedIn( ParserOutput $output ): bool {
		return $output->getExtensionData( self::EXTENSION_DATA_KEY ) === true;
	}

}
