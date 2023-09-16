<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SaveSchema;

use MediaWiki\Rest\RequestInterface;
use ProfessionalWiki\NeoWiki\Application\SchemaRepository;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use TitleFactory;
use Title;

class SaveSchemaAction {

	public function __construct(
		private readonly SaveSchemaPresenter $saveSchemaPresenter,
		private readonly TitleFactory $titleFactory
	) {
	}

	public function saveSchema( string $schemaName, RequestInterface $request, bool $isFirstSaving = false ): void {

		$title = $this->createTitle( $schemaName );
		if ( !$title ) {
			return;
		}

		$content = $this->createContent( $request );
		if ( !$content ) {
			return;
		}

		$schemaRepository = NeoWikiExtension::getInstance()->newSchemaRepository( $title );

		if ( $isFirstSaving ) {
			$this->createSchema( $title, $content, $schemaRepository );
			return;
		}
		$this->updateSchema( $title, $content, $schemaRepository );
	}

	private function createSchema( Title $title, string $content, SchemaRepository $repository ): void {

		if ( $title->isKnown() ) {
			$this->saveSchemaPresenter->presentError( 'The schema name "' . $title->getText() . '" already exists.' );
			return;
		}

		$revision = $repository->saveSchema( $title, $content, (int)EDIT_NEW );

		if ( $revision ) {
			return;
		}

		$this->saveSchemaPresenter->presentError( 'No new schema can be created at this time' );
	}

	private function updateSchema( Title $title, string $content, SchemaRepository $repository ): void {

		if ( !$title->isKnown() ) {
			$this->saveSchemaPresenter->presentError( 'The schema name "' . $title->getText() . '" does not exist.' );
			return;
		}

		$revision = $repository->saveSchema( $title, $content, (int)EDIT_UPDATE );

		if ( $revision ) {
			return;
		}

		$this->saveSchemaPresenter->presentError( 'The schema can not be updated at this time' );
	}

	private function createTitle( string $schemaName ): Title|null {

		if ( !$schemaName ) {
			$this->saveSchemaPresenter->presentError( 'The schema name is required.' );
			return null;
		}

		$title = $this->titleFactory->newFromText(
			'Schema:' . $schemaName,
			NeoWikiExtension::NS_SCHEMA
		);

		if ( !$title || !$title->canExist() ) {
			$this->saveSchemaPresenter->presentError( 'The schema name is invalid.' );
			return null;
		}

		return $title;
	}

	private function createContent( RequestInterface $request ): string|null {

		$requestData = json_decode( $request->getBody()->getContents(), true );
		if ( !is_array( $requestData ) || empty( $requestData ) ) {
			$this->saveSchemaPresenter->presentError( 'The propertyDefinitions are required.' );
			return null;
		}

		$this->saveSchemaPresenter->presentSchema( $requestData );

		$result = $this->saveSchemaPresenter->getJsonArray();

		if ( !empty( $result[ 'success' ] ) ) {
			return $this->saveSchemaPresenter->getJson();
		}

		return null;
	}
}
