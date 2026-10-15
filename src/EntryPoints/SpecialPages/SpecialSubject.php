<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectSlotReader;
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

		if ( $subPage === null || $subPage === '' ) {
			$this->getOutput()->redirect( SpecialPage::getTitleFor( 'Subjects' )->getFullURL() );
			return;
		}

		$this->addHelpLink( DocumentationUrl::Subjects->value, true );

		$out = $this->getOutput();
		$extension = NeoWikiExtension::getInstance();
		$subjectId = $extension->getSubjectIdParser()->parse( $subPage );
		$attributes = [ 'id' => 'ext-neowiki-subject' ];

		if ( $subjectId !== null ) {
			$pageShowingSubject = $this->pageShowingSubject( $extension, $subjectId );

			if ( $pageShowingSubject !== null ) {
				$out->redirect( $pageShowingSubject->getFullURL() );
				return;
			}

			$this->headBySubject( $out, $extension, $subjectId );

			// What the Subject's own view reads; it loads every Mapping the viewer may read.
			$out->addJsConfigVars( $extension->getSubjectUiJsConfigVars( $this->getAuthority() ) );
			$attributes['data-mw-neowiki-subject-id'] = $subjectId->text;
		}
		else {
			$out->addHTML(
				Html::errorBox( $this->msg( 'neowiki-special-subject-invalid-id' )->escaped() )
			);
		}

		// The frontend fills this element with the Subject asked for. What it shows is decided by the read
		// it makes, on the same terms as the read behind the title: a Subject that does not exist and one
		// on a page this user may not read answer alike (#1046).
		$extension->newFrontendModuleLoader()->load( $out, $this->getSkin() );
		$out->addHTML( Html::element( 'div', $attributes ) );
	}

	/**
	 * The page that shows the Subject in full as this one does, beside the page's own history and tabs:
	 * on a subject-first wiki, a content page does so for its Main Subject while automatic rendering is
	 * on. Null for any other Subject, and for a page the reader may not read.
	 */
	private function pageShowingSubject( NeoWikiExtension $extension, SubjectId $subjectId ): ?Title {
		if ( !$extension->isSubjectFirst() || !$extension->shouldAutoRenderMainSubject() ) {
			return null;
		}

		$page = $extension->newSubjectHostingPageResolver( $this->getAuthority() )->resolveReadableHostingPage( $subjectId );
		$title = $page === null ? null : Title::newFromText( $page->getTitle() );

		if ( $title === null
			|| $title->isRedirect()
			|| !MediaWikiServices::getInstance()->getNamespaceInfo()->isContent( $title->getNamespace() )
			|| !$this->isMainSubjectOfPublishedLatestRevision( $extension, $title, $subjectId )
		) {
			return null;
		}

		return $title;
	}

	/**
	 * Readers see the revision the wiki publishes, and a page shows its Main Subject in full only on its
	 * latest revision.
	 */
	private function isMainSubjectOfPublishedLatestRevision( NeoWikiExtension $extension, Title $title, SubjectId $subjectId ): bool {
		$latest = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title );

		return $latest !== null
			&& $extension->getRevisionPolicy()->publishedRevision( $latest )?->getId() === $latest->getId()
			&& SubjectSlotReader::read( $latest )?->getPageSubjects()->isMainSubject( $subjectId ) === true;
	}

	/**
	 * Heads the page by the Subject's own name, and with it the browser tab, a bookmark and a history
	 * entry: this page is where a concept URI leads, so those should name the thing rather than the page
	 * showing it. A Dependent Subject is headed by its Schema and Host Subject, as in "Birth of Pablo Picasso", and
	 * any other Subject nobody named by "No label defined" and its id. A Subject this wiki does not hold leaves the
	 * page's description standing, and so, indistinguishably, does one on a page the reader may not read (#1046).
	 */
	private function headBySubject( OutputPage $out, NeoWikiExtension $extension, SubjectId $subjectId ): void {
		$presenter = new SubjectNamePresenter();

		$extension->newGetSubjectQuery( $presenter, $this->getAuthority() )->execute(
			subjectId: $subjectId->text,
			includePageIdentifiers: false,
			includeReferencedSubjects: false
		);

		$subject = $presenter->getRequestedSubject();

		if ( $subject === null ) {
			return;
		}

		if ( $subject->hostSubject !== null ) {
			$out->setPageTitleMsg( SubjectNameMessage::ofDependentSubject( $this, $subject->schema, $subject->hostSubject ) );
			return;
		}

		if ( $subject->displayNameIsGenerated ) {
			SubjectLabelHtml::headPage( $out, null, $subjectId );
			return;
		}

		$out->setPageTitleMsg( SubjectNameMessage::from( $this, $subjectId, $subject->displayName ) );
	}

	public function getGroupName(): string {
		return 'neowiki';
	}

	public function getDescription(): Message {
		return $this->msg( 'neowiki-special-subject' );
	}

}
