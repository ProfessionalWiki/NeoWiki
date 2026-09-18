<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\SubjectNameMessage;
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

		$extension = NeoWikiExtension::getInstance();
		$subjectId = $extension->getSubjectIdParser()->parse( $subPage ?? '' );

		if ( $subjectId !== null ) {
			$name = $this->subjectName( $extension, $subjectId );

			if ( $name !== null ) {
				$this->getOutput()->setPageTitleMsg( $name );
			}

			// What the Subject's own view reads. The lookup shown without one reads none of it, and
			// each read costs a permission check per Mapping page.
			$this->getOutput()->addJsConfigVars( $extension->getSubjectUiJsConfigVars( $this->getAuthority() ) );
		}
		elseif ( $subPage !== null && $subPage !== '' ) {
			$this->getOutput()->addHTML(
				Html::errorBox( $this->msg( 'neowiki-special-subject-invalid-id' )->escaped() )
			);
		}

		$this->showMountPoint( $extension, $subjectId );
	}

	/**
	 * The frontend fills this element with the Subject asked for, or — with no id to fill it from —
	 * with a lookup for choosing one.
	 *
	 * What the body shows for an id is decided by the read the frontend makes, on the same terms as
	 * the read behind the title: a Subject that does not exist and one on a page this user may not
	 * read answer alike (#1046).
	 */
	private function showMountPoint( NeoWikiExtension $extension, ?SubjectId $subjectId ): void {
		$out = $this->getOutput();

		$extension->newFrontendModuleLoader()->load( $out, $this->getSkin() );

		$attributes = [ 'id' => 'ext-neowiki-subject' ];

		if ( $subjectId !== null ) {
			$attributes['data-mw-neowiki-subject-id'] = $subjectId->text;
		}

		$out->addHTML( Html::element( 'div', $attributes ) );
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

		return $displayName === null
			? null
			: SubjectNameMessage::from( $this, $displayName, $presenter->displayNameIsGenerated() );
	}

	public function getGroupName(): string {
		return 'neowiki';
	}

	public function getDescription(): Message {
		return $this->msg( 'neowiki-special-subject' );
	}

}
