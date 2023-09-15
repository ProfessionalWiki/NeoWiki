<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

class BlockDefinitionContent extends \JsonContent {

	public const CONTENT_MODEL_ID = 'BlockDefinition';

	public function __construct( string $text, string $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct(
			$text,
			$modelId
		);
	}

}
