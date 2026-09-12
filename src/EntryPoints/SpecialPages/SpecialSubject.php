<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\SubjectNamePresenter;

class SpecialSubject extends SpecialPage {

	public function __construct() {
		parent::__construct( 'Subject' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );

		$out = $this->getOutput();
		$extension = NeoWikiExtension::getInstance();
		$subjectId = $extension->getSubjectIdParser()->parse( $subPage ?? '' );

		if ( $subjectId === null ) {
			$out->addHTML(
				Html::errorBox( $this->msg( 'neowiki-special-subject-invalid-id' )->escaped() )
			);
			return;
		}

		$name = $this->subjectName( $extension, $subjectId );

		if ( $name !== null ) {
			$out->setPageTitleMsg( $name );
		}

		// What the body shows is decided by the read the frontend makes, on the same terms as the read
		// behind the title: a Subject that does not exist and one on a page this user may not read
		// answer alike (#1046).
		$extension->newFrontendModuleLoader()->load( $out, $this->getSkin() );
		$out->addJsConfigVars( $extension->getSubjectUiJsConfigVars( $this->getAuthority() ) );

		$out->addHTML( Html::element( 'div', [
			'id' => 'ext-neowiki-subject',
			'data-mw-neowiki-subject-id' => $subjectId->text,
		] ) );
	}

	/**
	 * The Subject's own name, for the H1 and with it the browser tab, a bookmark and a history entry:
	 * this page is where a concept URI leads, so those should name the thing rather than the page
	 * showing it. Null leaves the page's description standing, which is what a Subject this wiki does
	 * not hold gets — and, indistinguishably, one on a page the reader may not read (#1046).
	 */
	private function subjectName( NeoWikiExtension $extension, SubjectId $subjectId ): ?Message {
		$presenter = new SubjectNamePresenter();

		$extension->newGetSubjectQuery( $presenter, $this->getAuthority() )->execute(
			subjectId: $subjectId->text,
			includePageIdentifiers: false,
			includeReferencedSubjects: false
		);

		$displayName = $presenter->getDisplayName();

		if ( $displayName === null ) {
			return null;
		}

		if ( $presenter->displayNameIsGenerated() ) {
			return $this->msg( 'neowiki-subject-generated-name' )->plaintextParams( $displayName );
		}

		return ( new RawMessage( '$1' ) )->plaintextParams( $displayName );
	}

	public function getGroupName(): string {
		return 'neowiki';
	}

	public function getDescription(): Message {
		return $this->msg( 'neowiki-special-subject' );
	}

}
