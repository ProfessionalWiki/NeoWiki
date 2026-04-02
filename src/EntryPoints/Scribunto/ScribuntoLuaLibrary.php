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
			'getMainSubject' => [ $this, 'getMainSubject' ],
			'getSubject' => [ $this, 'getSubject' ],
			'getChildSubjects' => [ $this, 'getChildSubjects' ],
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

	public function getMainSubject( ?string $pageName = null ): array {
		$this->checkTypeOptional( 'mw.neowiki.getMainSubject', 1, $pageName, 'string', null );

		if ( $pageName !== null ) {
			$this->incrementExpensiveFunctionCount();
		}

		return $this->getSubjectDataLookup()->getMainSubjectData( $this->getTitle(), $pageName );
	}

	public function getSubject( ?string $subjectId = null ): array {
		$this->checkType( 'mw.neowiki.getSubject', 1, $subjectId, 'string' );
		$this->incrementExpensiveFunctionCount();

		return $this->getSubjectDataLookup()->getSubjectData( $subjectId );
	}

	public function getChildSubjects( ?string $pageName = null ): array {
		$this->checkTypeOptional( 'mw.neowiki.getChildSubjects', 1, $pageName, 'string', null );

		if ( $pageName !== null ) {
			$this->incrementExpensiveFunctionCount();
		}

		return $this->getSubjectDataLookup()->getChildSubjectsData( $this->getTitle(), $pageName );
	}

}
