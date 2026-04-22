<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Actions;

use FormlessAction;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class ManageSubjectsAction extends FormlessAction {

	public const string ACTION_NAME = 'managesubjects';

	public function getName(): string {
		return self::ACTION_NAME;
	}

	public function getRestriction(): string {
		return 'read';
	}

	public function requiresWrite(): bool {
		return false;
	}

	public function onView(): string {
		$out = $this->getOutput();
		$title = $out->getTitle();

		if ( !self::isEligibleTitle( $title ) ) {
			return Html::errorBox(
				$this->msg( 'neowiki-managesubjects-not-applicable' )->escaped()
			);
		}

		$out->setPageTitleMsg(
			$this->msg( 'neowiki-managesubjects-title', $title->getPrefixedText() )
		);

		$out->addModuleStyles( [ 'ext.neowiki.styles' ] );
		$out->addModules( [ 'ext.neowiki' ] );

		$out->addJsConfigVars( [
			'wgNeoWikiManageSubjectsPageId' => $title->getArticleID(),
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
