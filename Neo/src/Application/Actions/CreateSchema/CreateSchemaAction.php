<?php

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSchema;

use InvalidArgumentException;
use MediaWiki\Revision\SlotRecord;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\Infrastructure\SchemaAuthorizer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use Title;

/**
 * FIXME: this class binds to a bunch of MediaWiki-specific classes.
 * TODO: re-add CreateSchemaActionTest
 */
class CreateSchemaAction {

	public function __construct(
		private readonly CreateSchemaPresenter $presenter,
		private readonly PageContentSaver $pageContentSaver,
		private readonly SchemaLookup $schemaLookup,
		private readonly SchemaPersistenceDeserializer $deserializer,
		private readonly SchemaAuthorizer $authorizer
	) {
	}

	public function execute( string $name, string $schemaJson ): void {
		if ( !$this->authorizer->canCreateSchema() ) {
			$this->presenter->presentPermissionsDenied();
			return;
		}

		try {
			$schemaName = new SchemaName( $name );
		} catch ( InvalidArgumentException ) {
			$this->presenter->presentInvalidTitle();
			return;
		}

		$existingSchema = $this->schemaLookup->getSchema( $schemaName );
		if ( $existingSchema !== null ) {
			$this->presenter->presentSchemaAlreadyExists();
			return;
		}

		try {
			$this->deserializer->deserialize( $schemaName, $schemaJson );
		} catch ( \Exception ) {
			$this->presenter->presentInvalidArguments();
			return;
		}

		$title = Title::makeTitleSafe( NS_NEOWIKI_SCHEMA, $name );
		if ( $title === null ) {
			$this->presenter->presentInvalidTitle();
			return;
		}

		$savingResult = $this->pageContentSaver->saveContent(
			page: $title,
			contentBySlot: [ SlotRecord::MAIN => new SchemaContent( $schemaJson ) ],
			comment: \CommentStoreComment::newUnsavedComment( 'The schema is created' )
		);

		$this->presentSavingResult( $savingResult, $schemaJson );
	}

	private function presentSavingResult( PageContentSavingStatus $result, string $schemaJson ): void {
		if ( $result->status === PageContentSavingStatus::ERROR ) {
			$this->presenter->presentSchemaCreationError( $result->errorMessage );
			return;
		}

		if ( $result->status === PageContentSavingStatus::NO_CHANGES ) {
			$this->presenter->presentNoChanges();
			return;
		}

		if ( $result->status === PageContentSavingStatus::REVISION_CREATED ) {
			$this->presenter->presentSchema( $schemaJson );
		}
	}

}
