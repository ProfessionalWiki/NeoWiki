<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use MediaWiki\Content\JsonContent;

class SchemaContent extends JsonContent {

	public const string CONTENT_MODEL_ID = 'NeoWikiSchema';

	public function __construct( string $text, string $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct(
			$text,
			$modelId
		);
	}

}
