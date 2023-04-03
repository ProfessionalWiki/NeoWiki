<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\LuaGetSubjectPresenter;
use Scribunto_LuaLibraryBase;

class NeoWikiLua extends Scribunto_LuaLibraryBase {

	public function register(): array {
		return $this->getEngine()->registerInterface(
			__DIR__ . '/../NeoWiki.lua',
			[
				'getSubject' => fn( string $subjectId ): array => [ $this->getSubject( $subjectId ) ],
				'getLabel' => fn( string $subjectId ): array => [ $this->getLabel( $subjectId ) ],
			]
		);
	}

	private function getSubject( string $subjectId ): array {
		$presenter = new LuaGetSubjectPresenter();
		$query = NeoWikiExtension::getInstance()->newGetSubjectQuery( $presenter );

		$query->execute( $subjectId );

		return $presenter->getLuaResponse();
	}

	private function getLabel( string $subjectId ): string {
		return $this->getSubject( $subjectId )['label'] ?? '';
	}

}
