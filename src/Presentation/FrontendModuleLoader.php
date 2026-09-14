<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\User;
use Skin;

class FrontendModuleLoader {

	/** What RightsBasedSubjectPermissionHints requires for a Subject page, in the order a missing one is reported. */
	private const SUBJECT_PAGE_CREATION_RIGHTS = [ 'createpage', 'edit' ];

	public function __construct(
		private readonly HookContainer $hookContainer,
		private readonly int $validationDebounceMs,
		private readonly bool $validationEnforced,
	) {
	}

	public function load( OutputPage $out, Skin $skin ): void {
		$out->addModules( 'ext.neowiki' );
		$out->addModuleStyles( 'ext.neowiki.styles' );

		$out->addJsConfigVars( [
			'wgNeoWikiValidationDebounceMs' => $this->validationDebounceMs,
			'wgNeoWikiEnforceValidation' => $this->validationEnforced,
			'wgNeoWikiCreateSubjectPageDeniedReason' => $this->subjectPageCreationDeniedReason( $out ),
		] );

		/** @var list<string> $modules populated by hook handlers */
		$modules = [];
		$this->hookContainer->run( 'NeoWikiGetFrontendModules', [ &$modules, $out, $skin ] );

		$out->addModules( $modules );
	}

	/**
	 * Why this viewer may not create a Subject page, or null when they may; shown behind the
	 * {{#create_subject}} button's click.
	 */
	private function subjectPageCreationDeniedReason( OutputPage $out ): ?string {
		$missingRight = $this->firstMissingRight( $out->getAuthority() );

		if ( $missingRight === null ) {
			return null;
		}

		$reason = User::newFatalPermissionDeniedStatus( $missingRight )->getMessages()[0];

		return $this->withoutLinkMarkup( $out->msg( $reason )->text() );
	}

	private function firstMissingRight( Authority $authority ): ?string {
		foreach ( self::SUBJECT_PAGE_CREATION_RIGHTS as $right ) {
			if ( !$authority->isAllowed( $right ) ) {
				return $right;
			}
		}

		return null;
	}

	/**
	 * The reason names the groups that hold the right as wiki links. It is shown as plain text, and
	 * parsing it into that would cost a parser run and a link lookup on every page view.
	 */
	private function withoutLinkMarkup( string $text ): string {
		return preg_replace( '/\[\[(?:[^|\]]*\|)?([^\]]*)\]\]/', '$1', $text ) ?? $text;
	}

	/**
	 * Loads the frontend for a page that holds Subjects, telling it whether this viewer may edit
	 * them. The frontend has only the wiki-global edit right to go on otherwise, so on a page the
	 * viewer cannot edit it would offer writes the server then rejects.
	 */
	public function loadWithSubjectPermissions( OutputPage $out, Skin $skin, bool $canEditPageSubjects ): void {
		$this->load( $out, $skin );

		$out->addJsConfigVars( [
			'wgNeoWikiCanEditPageSubjects' => $canEditPageSubjects,
		] );
	}

}
