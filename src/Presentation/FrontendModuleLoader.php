<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\PermissionStatus;
use Skin;

class FrontendModuleLoader {

	/**
	 * What RightsBasedSubjectPermissionHints requires for a Subject page, in the order a missing one is reported:
	 * every button needs edit, while only a button that creates a page needs createpage.
	 */
	private const array SUBJECT_PAGE_CREATION_RIGHTS = [ 'edit', 'createpage' ];

	public function __construct(
		private readonly HookContainer $hookContainer,
		private readonly int $validationDebounceMs,
		private readonly bool $validationEnforced,
		private readonly bool $subjectFirst,
	) {
	}

	public function load( OutputPage $out, Skin $skin ): void {
		$out->addModules( 'ext.neowiki' );
		$out->addModuleStyles( 'ext.neowiki.styles' );

		$out->addJsConfigVars( [
			'wgNeoWikiValidationDebounceMs' => $this->validationDebounceMs,
			'wgNeoWikiEnforceValidation' => $this->validationEnforced,
			'wgNeoWikiSubjectFirst' => $this->subjectFirst,
		] );

		/** @var list<string> $modules populated by hook handlers */
		$modules = [];
		$this->hookContainer->run( 'NeoWikiGetFrontendModules', [ &$modules, $out, $skin ] );

		$out->addModules( $modules );
	}

	/**
	 * Tells the {{#create_subject}} button why this viewer may not create a Subject page, or null when
	 * they may. Only a page carrying the button needs it, since it is shown behind the button's click.
	 */
	public function loadCreateSubjectPageDeniedReason( OutputPage $out ): void {
		$out->addJsConfigVars( [
			'wgNeoWikiCreateSubjectPageDeniedReason' => $this->subjectPageCreationDeniedReason( $out ),
		] );
	}

	private function subjectPageCreationDeniedReason( OutputPage $out ): ?string {
		$status = PermissionStatus::newEmpty();

		foreach ( self::SUBJECT_PAGE_CREATION_RIGHTS as $right ) {
			if ( !$out->getAuthority()->isAllowed( $right, $status ) ) {
				$messages = $status->getMessages();

				// An Authority need not say why; the button then states a reason of its own.
				return $messages === [] ? null : $this->withoutLinkMarkup( $out->msg( $messages[0] )->text() );
			}
		}

		return null;
	}

	/**
	 * The reason names the groups that hold the right as wiki links. It is shown as plain text, and
	 * parsing it into that would cost a parser run and a link lookup on every view of a page with a button.
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
