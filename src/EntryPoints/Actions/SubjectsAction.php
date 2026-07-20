<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Actions;

use FormlessAction;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class SubjectsAction extends FormlessAction {

	public const string ACTION_NAME = 'subjects';

	public function getName(): string {
		return self::ACTION_NAME;
	}

	public function getRestriction(): string {
		return 'read';
	}

	public function requiresWrite(): bool {
		return false;
	}

	public function getPageTitle(): Message {
		return $this->msg( 'neowiki-managesubjects-title', $this->getTitle()->getPrefixedText() );
	}

	protected function getDescription(): string {
		return '';
	}

	public function onView(): string {
		$out = $this->getOutput();
		$title = $out->getTitle();

		if ( !self::isEligibleTitle( $title ) ) {
			return Html::errorBox(
				$this->msg( 'neowiki-managesubjects-not-applicable' )->escaped()
			);
		}

		$extension = NeoWikiExtension::getInstance();
		$extension->newFrontendModuleLoader()->load( $out, $this->getSkin() );

		$out->addJsConfigVars( [
			'wgNeoWikiManageSubjectsPageId' => $title->getArticleID(),
			// The export UI (Data tab menus) is driven by this list. Filtered by the viewing user's
			// read authority so restricted Mapping page titles never reach a reader who cannot see them.
			'wgNeoWikiRdfProjections' => $extension->filterReadableProjectionNames(
				$extension->getRdfProjectionNames(),
				$this->getAuthority()
			),
			// The copy-IRI control appends the Subject id to this base to show the full neo-subj:
			// concept URI, deriving it from the same server-side rule the RDF export mints IRIs with.
			'wgNeoWikiSubjectIriBase' => $extension->getRdfNamespaces()->subjectIriBase(),
		] );

		return Html::element( 'div', [ 'id' => 'ext-neowiki-manage-subjects' ] );
	}

	public static function isEligibleTitle( ?Title $title ): bool {
		if ( $title === null || !$title->canExist() || $title->getArticleID() === 0 ) {
			return false;
		}

		return MediaWikiServices::getInstance()
			->getNamespaceInfo()
			->isContent( $title->getNamespace() );
	}

}
