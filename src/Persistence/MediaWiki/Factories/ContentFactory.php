<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Factories;

use Content;
use ContentHandler;
use MWContentSerializationException;
use MWException;
use Title;

class ContentFactory {

	/**
	 * @throws MWContentSerializationException
	 * @throws MWException
	 */
	public function create( string $data, Title $title ): Content {
		return ContentHandler::makeContent( $data, $title );
	}
}
