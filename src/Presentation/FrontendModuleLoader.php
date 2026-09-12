<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Output\OutputPage;
use Skin;

class FrontendModuleLoader {

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
		] );

		/** @var list<string> $modules populated by hook handlers */
		$modules = [];
		$this->hookContainer->run( 'NeoWikiGetFrontendModules', [ &$modules, $out, $skin ] );

		$out->addModules( $modules );
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
