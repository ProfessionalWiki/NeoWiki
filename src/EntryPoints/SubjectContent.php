<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use FormatJson;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentData;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentDataSerializer;

class SubjectContent extends \JsonContent {

	public const CONTENT_MODEL_ID = 'NeoWikiSubject';

	public function __construct( string $text, string $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct(
			$text,
			$modelId
		);
	}

	public static function newFromData( SubjectContentData $data ): self {
		return new self( ( new SubjectContentDataSerializer() )->serialize( $data ) );
	}

	public static function newEmpty(): self {
		return self::newFromData( SubjectContentData::newEmpty() );
	}

	public function beautifyJSON(): string {
		return FormatJson::encode( json_decode( $this->getText() ), true, FormatJson::UTF8_OK );
	}

	public function hasSubjects(): bool {
		return $this->getContentData()->hasSubjects();
	}

	public function isEmpty(): bool {
		return $this->getContentData()->isEmpty();
	}

	public function setContentData( SubjectContentData $data ): void {
		$this->mText = ( new SubjectContentDataSerializer() )->serialize( $data );
	}

	public function getContentData(): SubjectContentData {
		return ( new SubjectContentDataDeserializer() )->deserialize( $this->getText() );
	}

}
