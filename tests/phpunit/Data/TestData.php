<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser;

class TestData {

	public const string LOCAL_SOURCE_KEY = 'testwiki';

	public static function getFileContents( string $fileName ): string {
		return file_get_contents( __DIR__ . '/../../../DemoData/' . $fileName );
	}

	public static function newSubjectIdParser(): SubjectIdParser {
		return new SubjectIdParser( self::LOCAL_SOURCE_KEY );
	}

}
