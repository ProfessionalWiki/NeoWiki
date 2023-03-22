<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use FormatJson;
use ProfessionalWiki\NeoWiki\Domain\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\SubjectSlotDeserializer;

class SubjectContent extends \JsonContent {

	public const CONTENT_MODEL_ID = 'NeoWikiSubject';

	public function __construct( string $text, string $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct(
			$text,
			$modelId
		);
	}

	public function beautifyJSON(): string {
		return FormatJson::encode( json_decode( $this->getText() ), true, FormatJson::UTF8_OK );
	}

	public function getSubjects(): SubjectMap {
		return ( new SubjectSlotDeserializer() )->deserialize( $this->getText() );
	}

}
