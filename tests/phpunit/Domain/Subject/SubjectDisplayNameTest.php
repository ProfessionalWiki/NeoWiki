<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName
 */
class SubjectDisplayNameTest extends TestCase {

	private function displayName( ?SubjectLabel $label, bool $isMainSubject, string $pageName = 'Page name' ): string {
		return SubjectDisplayName::forSubject(
			label: $label,
			isMainSubject: $isMainSubject,
			pageName: $pageName,
			schemaName: new SchemaName( 'Schema name' )
		);
	}

	public function testStoredLabelWinsForMainSubject(): void {
		$this->assertSame(
			'Stored',
			$this->displayName( new SubjectLabel( 'Stored' ), true )
		);
	}

	public function testStoredLabelWinsForChildSubject(): void {
		$this->assertSame(
			'Stored',
			$this->displayName( new SubjectLabel( 'Stored' ), false )
		);
	}

	public function testMainSubjectWithoutLabelFallsBackToPageName(): void {
		$this->assertSame(
			'Page name',
			$this->displayName( null, true )
		);
	}

	public function testChildSubjectWithoutLabelFallsBackToSchemaName(): void {
		$this->assertSame(
			'Schema name',
			$this->displayName( null, false )
		);
	}

	public function testMainSubjectWithoutPageNameFallsBackToSchemaName(): void {
		$this->assertSame(
			'Schema name',
			$this->displayName( null, true, '' )
		);
	}

	public function testSurroundingWhitespaceOfAStoredLabelIsPreserved(): void {
		$this->assertSame(
			'  Stored  ',
			$this->displayName( new SubjectLabel( '  Stored  ' ), false )
		);
	}

}
