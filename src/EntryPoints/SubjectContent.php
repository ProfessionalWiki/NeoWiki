<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use FormatJson;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectSlotDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectSlotSerializer;

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

	public function hasSubjects(): bool {
		return !$this->getSubjects()->isEmpty();
	}

	public function setSubjects( SubjectMap $subjects ): void {
		$this->mText = ( new SubjectSlotSerializer() )->serialize( $subjects );
	}

	/**
	 * TODO: add main subject and rename subjects to childSubjects
	 */
	public static function newFromSubjects( SubjectMap $subjects ): self {
		$content = new self( '' );
		$content->setSubjects( $subjects );
		return $content;
	}

}
