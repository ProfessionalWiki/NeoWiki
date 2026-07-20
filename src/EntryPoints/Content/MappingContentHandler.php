<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use InvalidArgumentException;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\ValidationParams;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingContentValidator;
use StatusValue;

class MappingContentHandler extends JsonContentHandler {

	protected function getContentClass(): string {
		return MappingContent::class;
	}

	public function validateSave( Content $content, ValidationParams $validationParams ): StatusValue {
		$status = parent::validateSave( $content, $validationParams );

		if ( !$status->isOK() ) {
			return $status;
		}

		$title = Title::newFromPageIdentity( $validationParams->getPageIdentity() );

		// The page title is the target/projection name, so a reserved name (native) is rejected here.
		try {
			new MappingName( $title->getText() );
		} catch ( InvalidArgumentException $exception ) {
			$status->fatal( 'neowiki-mapping-name-invalid', $exception->getMessage() );
		}

		$validator = MappingContentValidator::newInstance();

		if ( !$validator->validate( $content->getText() ) ) {
			$status->fatal( 'neowiki-mapping-invalid', count( $validator->getErrors() ) );

			foreach ( $validator->getErrors() as $pointer => $message ) {
				$status->fatal( 'neowiki-mapping-invalid-detail', $pointer, $message );
			}
		}

		return $status;
	}

	public function makeEmptyContent(): MappingContent {
		return new MappingContent( <<<JSON
{
	"version": 1,
	"prefixes": {},
	"schemas": {}
}
JSON
		);
	}

	public function canBeUsedOn( Title $title ): bool {
		return NamespaceContentModels::forNamespace( $title->getNamespace() ) === MappingContent::CONTENT_MODEL_ID;
	}

}
