<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

class BlocksContent extends \JsonContent {

	public const CONTENT_MODEL_ID = 'PageBlocks';
	public const SLOT_NAME = 'blocks';

	public function __construct( string $text, string $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct(
			$text,
			$modelId
		);
	}

}
