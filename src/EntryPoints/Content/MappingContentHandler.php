<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use InvalidArgumentException;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\ValidationParams;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\Mappings;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
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

			// The duplicate check deserializes the content, so only run it once the content is valid.
			return $status;
		}

		$this->rejectDuplicateTarget( $content, $title, $status );

		return $status;
	}

	/**
	 * One Mapping per (Schema, target): reject a save whose pair another Mapping page already claims.
	 * Lookup-based, so a concurrent save could still slip a duplicate through — acceptable pre-production,
	 * and the projector tie-breaks deterministically if it happens.
	 */
	private function rejectDuplicateTarget( Content $content, Title $title, StatusValue $status ): void {
		$extension = NeoWikiExtension::getInstance();

		try {
			$mapping = $extension->getMappingPersistenceDeserializer()
				->deserialize( new MappingName( $title->getText() ), $content->getText() );
		} catch ( InvalidArgumentException ) {
			return;
		}

		$conflict = ( new Mappings( $extension->getMappingLookup()->getAllMappings() ) )
			->conflictFor( $mapping->schema, $mapping->target, $mapping->name );

		if ( $conflict !== null ) {
			$status->fatal(
				'neowiki-mapping-duplicate-target',
				$mapping->schema->getText(),
				$mapping->target,
				$conflict->name->getText()
			);
		}
	}

	public function makeEmptyContent(): MappingContent {
		return new MappingContent( <<<JSON
{
	"version": 1,
	"schema": "",
	"target": "",
	"prefixes": {},
	"subject": {
		"class": ""
	},
	"properties": {}
}
JSON
		);
	}

	public function canBeUsedOn( Title $title ): bool {
		return $title->getNamespace() === NeoWikiExtension::NS_MAPPING;
	}

}
