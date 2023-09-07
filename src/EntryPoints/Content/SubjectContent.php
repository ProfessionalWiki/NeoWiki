<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use FormatJson;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataSerializer;

class SubjectContent extends \JsonContent {

	public const CONTENT_MODEL_ID = 'NeoWikiSubject';

	public function __construct( string $text, string $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct(
			$text,
			$modelId
		);
	}

	public static function newFromData( PageSubjects $data ): self {
		return new self( ( new SubjectContentDataSerializer() )->serialize( $data ) );
	}

	public static function newEmpty(): self {
		return self::newFromData( PageSubjects::newEmpty() );
	}

	public function beautifyJSON(): string {
		return FormatJson::encode( json_decode( $this->getText() ), true, FormatJson::UTF8_OK );
	}

	public function hasSubjects(): bool {
		return $this->getPageSubjects()->hasSubjects();
	}

	public function isEmpty(): bool {
		return $this->getPageSubjects()->isEmpty();
	}

	public function setPageSubjects( PageSubjects $data ): void {
		$this->mText = ( new SubjectContentDataSerializer() )->serialize( $data );
	}

	/**
	 * Returns a fresh instance of PageSubjects that does not have a reference to this content object.
	 */
	public function getPageSubjects(): PageSubjects {
		return NeoWikiExtension::getInstance()->newSubjectContentDataDeserializer()->deserialize( $this->getText() );
	}

	/**
	 * @param callable(PageSubjects):void $mutator
	 */
	public function mutatePageSubjects( callable $mutator ): void {
		$data = $this->getPageSubjects();
		$mutator( $data );
		$this->setPageSubjects( $data );
	}

}
