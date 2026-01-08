<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use HTMLForm;
use MediaWiki\Message\Message;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use SpecialPage;
use Title;

class SpecialNeoJson extends SpecialPage {

	public function __construct() {
		parent::__construct( 'NeoJson', listed: false );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );

		$title = $this->buildTitle( $subPage );

		if ( $title === null ) {
			$this->getOutput()->addWikiMsg( 'neowiki-todo-page-does-not-exist' ); // TODO
			return;
		}

		$json = $this->getNeoJson( $title );
		$this->addHtmlForm( $json, $title );
	}

	private function buildTitle( ?string $subPage ): ?Title {
		if ( $subPage === null ) {
			return null;
		}

		$title = Title::newFromText( $subPage );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		return $title;
	}

	private function getNeoJson( Title $title ): string {
		$content = NeoWikiExtension::getInstance()->newSubjectContentRepository()->getSubjectContentByPageTitle( $title );
		return $content?->getText() ?? '';
	}

	private function addHtmlForm( string $json, Title $title ): void {
		$form = new HTMLForm( $this->getFormFields( $json ), $this->getContext() );
		$form->setSubmitCallback( fn( array $formData ): bool => $this->onSubmit( $formData, $title ) );

		$submitResult = $form->show();

		if ( $submitResult === true ) {
			$this->getOutput()->redirect( $this->getPageTitle()->getSubpage( $title->getFullText() )->getFullURL() );
		}
	}

	private function getFormFields( string $json ): array {
		return [
			'json' => [
				'type' => 'textarea',
				'default' => $json,
				'rows' => 30,
				'cols' => 110
			]
		];
	}

	private function onSubmit( array $formData, Title $title ): bool {
		$content = SubjectContent::newFromData(
			NeoWikiExtension::getInstance()->newSubjectContentDataDeserializer()->deserialize( $formData['json'] )
		);

		NeoWikiExtension::getInstance()->newSubjectContentRepository()->editSubjectContent(
			$content,
			new PageId( $title->getId() ),
			'Update Subject'
		);

		return true;
	}

	public function getGroupName(): string {
		return 'neowiki'; // TODO
	}

	public function getDescription(): Message {
		return $this->msg(
			'neowiki-neojson-description',
			substr(
				$this->getContext()->getTitle()->getFullText(),
				strpos( $this->getContext()->getTitle()->getFullText(), '/' ) + 1
			)
		);
	}

}
