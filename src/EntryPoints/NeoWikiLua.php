<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Scribunto_LuaLibraryBase;

class NeoWikiLua extends Scribunto_LuaLibraryBase {

	public function register(): array {
		return $this->getEngine()->registerInterface(
			__DIR__ . '/../NeoWiki.lua',
			[
				'getLabel' => fn( string $subjectId ): array => [ $this->getLabel( $subjectId ) ],
			]
		);
	}

	private function getLabel( string $subjectId ): string {
		$subject = NeoWikiExtension::getInstance()->newSubjectRepository()->getSubject( new SubjectId( $subjectId ) );
		return $subject === null ? '' : $subject->label->text;
	}

}
