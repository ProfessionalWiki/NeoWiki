<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

class CypherContent extends \TextContent {

	public const CONTENT_MODEL_ID = 'NeoWikiCypherQuery';

	public function __construct( string $text, string $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct(
			$text,
			$modelId
		);
	}

}
