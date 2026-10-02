<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\DocumentationUrl;
use ProfessionalWiki\NeoWiki\Presentation\SubjectLabelHtml;
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
		$this->addHelpLink( DocumentationUrl::Subjects->value, true );

		$out = $this->getOutput();
		$extension = NeoWikiExtension::getInstance();
		$subjectId = $extension->getSubjectIdParser()->parse( $subPage ?? '' );
		$attributes = [ 'id' => 'ext-neowiki-subject' ];

		if ( $subjectId !== null ) {
			$this->headBySubject( $out, $extension, $subjectId );

			// What the Subject's own view reads. The picker shown without one reads none of it, and
			// each read costs a permission check per Mapping page.
			$out->addJsConfigVars( $extension->getSubjectUiJsConfigVars( $this->getAuthority() ) );
			$attributes['data-mw-neowiki-subject-id'] = $subjectId->text;
		}
		elseif ( $subPage !== null && $subPage !== '' ) {
			$out->addHTML(
				Html::errorBox( $this->msg( 'neowiki-special-subject-invalid-id' )->escaped() )
			);
		}

		// The frontend fills this element with the Subject asked for, or — with no id to fill it from —
		// with a picker for choosing one. What it shows for an id is decided by the read it makes, on
		// the same terms as the read behind the title: a Subject that does not exist and one on a page
		// this user may not read answer alike (#1046).
		$extension->newFrontendModuleLoader()->load( $out, $this->getSkin() );
		$out->addHTML( Html::element( 'div', $attributes ) );
	}

	/**
	 * Heads the page by the Subject's own name, and with it the browser tab, a bookmark and a history
	 * entry: this page is where a concept URI leads, so those should name the thing rather than the page
	 * showing it. A Subject nobody named is headed by "No label defined" and its id. A Subject this wiki
	 * does not hold leaves the page's description standing, and so, indistinguishably, does one on a
	 * page the reader may not read (#1046).
	 */
	private function headBySubject( OutputPage $out, NeoWikiExtension $extension, SubjectId $subjectId ): void {
		$presenter = new SubjectNamePresenter();

		$extension->newGetSubjectQuery( $presenter, $this->getAuthority() )->execute(
			subjectId: $subjectId->text,
			includePageIdentifiers: false,
			includeReferencedSubjects: false
		);

		$displayName = $presenter->getDisplayName();

		if ( $displayName === null ) {
			return;
		}

		if ( $presenter->displayNameIsGenerated() ) {
			SubjectLabelHtml::headPage( $out, null, $subjectId );
			return;
		}

		$out->setPageTitleMsg( SubjectNameMessage::from( $this, $subjectId, $displayName ) );
	}

	public function getGroupName(): string {
		return 'neowiki';
	}

	public function getDescription(): Message {
		return $this->msg( 'neowiki-special-subject' );
	}

}
