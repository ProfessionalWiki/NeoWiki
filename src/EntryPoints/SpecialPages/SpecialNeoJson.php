<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use PermissionsError;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

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

		// Deny early, before rendering the form, for users who cannot edit the page. This is only a
		// hint: the write itself is authorized in onSubmit().
		if ( !NeoWikiExtension::getInstance()->newSubjectPermissionHints( $this->getAuthority() )
				->canEditSubject( new PageId( $title->getId() ) ) ) {
			throw new PermissionsError( 'edit' );
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
		// This performs the Subject write, so it needs the binding authorization rather than the
		// hint that execute() gated the form on.
		if ( !NeoWikiExtension::getInstance()->newSubjectWriteAuthorizer( $this->getAuthority() )
				->authorize( new PageId( $title->getId() ) ) ) {
			throw new PermissionsError( 'edit' );
		}

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
