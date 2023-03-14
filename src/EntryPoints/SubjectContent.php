<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Status;

class SubjectContent extends \JsonContent {

	public const CONTENT_MODEL_ID = 'NeoWikiSubject';

	public function __construct( string $text, string $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct(
			$text,
			$modelId
		);
	}

	public function getData(): Status {
		$status = parent::getData();

		if ( $status->isGood() && !$this->isSubjectJson( $status->getValue() ) ) {
			return Status::newFatal( 'Invalid NeoWiki Subject' );
		}

		return $status;
	}

	private function isSubjectJson( $json ): bool {
		return true;
	}

}
