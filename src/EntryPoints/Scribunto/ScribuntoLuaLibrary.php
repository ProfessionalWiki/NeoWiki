<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Scribunto;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use ProfessionalWiki\NeoWiki\Application\Queries\SubjectDataLookup;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class ScribuntoLuaLibrary extends LibraryBase {

	private function getSubjectDataLookup(): SubjectDataLookup {
		$extension = NeoWikiExtension::getInstance();

		return new SubjectDataLookup(
			$extension->newSubjectContentRepository(),
			$extension->getSubjectRepository(),
		);
	}

	public function register(): array {
		$lib = [
			'getValue' => [ $this, 'getValue' ],
		];

		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.neowiki.lua', $lib, []
		);
	}

	public function getValue( ?string $propertyName = null, ?array $options = null ): array {
		$this->checkType( 'mw.neowiki.getValue', 1, $propertyName, 'string' );

		if ( $options !== null && ( isset( $options['page'] ) || isset( $options['subject'] ) ) ) {
			$this->incrementExpensiveFunctionCount();
		}

		return $this->getSubjectDataLookup()->getValue( $this->getTitle(), $propertyName, $options );
	}

}
