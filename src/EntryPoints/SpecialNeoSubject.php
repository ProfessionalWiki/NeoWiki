<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use CommentStoreComment;
use FormSpecialPage;
use HTMLForm;
use JsonContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MWException;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectRequest;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\FormCreateSubjectPresenter;
use ReflectionClass;
use RequestContext;
use Status;
use StatusValue;
use Title;
use WikitextContent;

class SpecialNeoSubject extends FormSpecialPage {

	private array $schema = [];
	private string $schemaName = '';

	public function __construct() {
		parent::__construct( 'NeoSubject' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		$title = $this->buildTitle( $subPage );

		if ( $title === null ) {
			$this->getOutput()->addWikiMsg( 'neowiki-todo-unknown-schema' ); // TODO
			return;
		}

		$this->schema = $this->getSchema( $title );
		$this->schemaName = $subPage;

		$this->getOutput()->addModules(
			[
				'oojs-ui-core',
				'oojs-ui-widgets',
				'oojs-ui-windows',
				'mediawiki.widgets.TagMultiselectWidget',
				'mediawiki.user'
			]
		);

		parent::execute( $subPage );
	}

	private function buildTitle( ?string $subPage ): ?Title {
		if ( $subPage === null ) {
			return null;
		}

		$title = Title::newFromText( $subPage, NS_NEOWIKI_SCHEMA );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		return $title;
	}

	private function getSchema( Title $title ): array {
		$content = NeoWikiExtension::getInstance()->newPageContentFetcher()->getPageContent( $title->getFullText() );

		if ( $content instanceof JsonContent ) {
			return (array)json_decode( $content->getText(), true );
		}

		return [];
	}

	protected function getFormFields(): array {
		return $this->getCommonFields() + $this->getAllSchemaFields();
	}

	private function getCommonFields(): array {
		return [
			'description' => [
				'type' => 'info',
				'default' => $this->schema['description'] ?? 'unknown',
			],
			'label' => [
				'type' => 'text',
				'label' => 'Title',
			]
		];
	}

	private function getAllSchemaFields(): array {
		$fields = [];

		$topLevelProperties = $this->schema['properties'] ?? [];

		foreach ( $topLevelProperties as $sectionName => $sectionConfig ) {
			$fields += $this->getSchemaSectionFields( $sectionName, $sectionConfig, );
		}

		return $fields;
	}

	private function getSchemaSectionFields( string $sectionName, array $sectionConfig ): array {
		$fields = [];

		foreach ( $sectionConfig['properties'] ?? [] as $propertyName => $propertyConfig ) {
			$fields[ $propertyName] = $this->getSchemaPropertyField( $propertyConfig ) + [
				'label' => $propertyName,
				'section' => $sectionName
			];
		}

		return $fields;
	}

	/**
	 * phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
	 */
	private function getSchemaPropertyField( array $config ): array {
		$type = $config['type'] ?? '';

		if ( $type === 'string' ) {
			return [
				'type' => 'text'
			];
		}

		if ( $type === 'number' ) {
			return [
				'type' => 'float',
				'min' => $config['minimum'] ?? null,
				'max' => $config['maximum'] ?? null,
				'infusable' => true,
				'cssclass' => 'mw-htmlform-autoinfuse'
			];
		}

		if ( $type === 'integer' ) {
			return [
				'type' => 'int',
				'min' => $config['minimum'] ?? null,
				'max' => $config['maximum'] ?? null,
				'infusable' => true,
				'cssclass' => 'mw-htmlform-autoinfuse'
			];
		}

		if ( $type === 'boolean' ) {
			return [
				'type' => 'toggle',
			];
		}

		if ( $type === 'null' ) {
			return [];
		}

		if ( $type === 'array' ) {
			return [
				'type' => 'tagmultiselect',
				'allowArbitrary' => true,
				'infusable' => true,
				'cssclass' => 'mw-htmlform-autoinfuse'
			];
		}

		// TODO: object type is 'cloner', but probably too complex. Maybe only for 'relations'.
		return [
			'type' => 'info',
			'default' => 'unknown'
		];
	}

	protected function getForm(): ?HtmlForm {
		$form = parent::getForm();

		// TODO: Hack to move fields without section to the top.
		$reflection = new ReflectionClass( HtmlForm::class );
		$property = $reflection->getProperty( 'mSubSectionBeforeFields' );
		$property->setValue( $form, false );

		return $form;
	}

	public function onSubmit( array $formData ): bool|Status {
		// TODO: for now handle only the 'properties' fields

		// First create the page
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $formData['label'] ) );
		$exists = $page->exists();

		try {
			if ( !$page->exists() ) {
				$updater = $page->newPageUpdater( RequestContext::getMain()->getUser() );
				$updater->setContent( SlotRecord::MAIN, new WikitextContent( 'TODO {{#:NeoBox|' . $this->schemaName . '}}' ) );
				$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Created ' . $this->schemaName ) );
			}
		} catch ( MWException $e ) {
			return Status::wrap( StatusValue::newFatal( $e->getMessage() ) );
		}

		// TODO: process fields according to schema
		$processedFormData = array_map(
			fn ( string $value ) => str_contains( $value, "\n" ) ? explode( "\n", $value ) : $value,
			$formData,
		);

		if ( $exists ) {
			return Status::wrap( StatusValue::newFatal( 'TODO: update existing subject' ) );
		} else {
			$this->createMainSubject( $page->getTitle()->getId(), $processedFormData );
		}

		$this->getOutput()->redirect( $page->getTitle()->getFullURL() );

		return true;
	}

	private function createMainSubject( int $pageId, array $formData ): void {
		NeoWikiExtension::getInstance()->newCreateSubjectAction( new FormCreateSubjectPresenter() )->createSubject(
			new CreateSubjectRequest(
				pageId: $pageId,
				isMainSubject: true,
				label: $formData['label'],
				types: [ $this->schemaName ],
				properties: array_diff( $formData, [ 'label' ] ),
			)
		);
	}

	public function getGroupName(): string {
		return 'neowiki'; // TODO
	}

	public function getDescription(): string {
		// TODO
		return 'Create new ' . $this->schemaName;
	}

	protected function getDisplayFormat() {
		return 'ooui';
	}

}
