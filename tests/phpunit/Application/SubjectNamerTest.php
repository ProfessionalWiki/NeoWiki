<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SubjectNamer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\SubjectNamer
 */
class SubjectNamerTest extends TestCase {

	private const string TITLE = 'Madonna and Child on a Cloud';

	public function testOwnLabelIsTheTemplateLabelOfASubjectWithoutAStoredLabel(): void {
		$this->assertSame( self::TITLE, $this->newNamer()->ownLabel( $this->artwork( label: null ) ) );
	}

	private function newNamer( bool $withSchema = true ): SubjectNamer {
		return TestSources::newSubjectNamer(
			$withSchema
				? new InMemorySchemaLookup( TestSchema::build(
					name: 'Artwork',
					properties: new PropertyDefinitions( [ 'Title' => TestProperty::buildText() ] ),
					labelTemplate: '{Title}'
				) )
				: new InMemorySchemaLookup()
		);
	}

	private function artwork( ?string $label ): Subject {
		return TestSubject::build(
			label: $label,
			schemaName: new SchemaName( 'Artwork' ),
			statements: new StatementList( [ TestStatement::build( property: 'Title', value: self::TITLE ) ] )
		);
	}

	public function testOwnLabelIsTheStoredLabelWhereThereIsOne(): void {
		$this->assertSame( 'Madonna', $this->newNamer()->ownLabel( $this->artwork( label: 'Madonna' ) ) );
	}

	public function testSubjectWhoseSchemaCannotBeFoundHasNoOwnLabel(): void {
		$this->assertNull( $this->newNamer( withSchema: false )->ownLabel( $this->artwork( label: null ) ) );
	}

	public function testChosenNameReadsTheTemplateLabel(): void {
		$subject = $this->artwork( label: null );

		$this->assertSame(
			self::TITLE,
			$this->newNamer()->chosenName( $subject, new PageSubjects( null, new SubjectMap( $subject ) ), 'Page name' )
		);
	}

	public function testDisplayNameReadsTheTemplateLabel(): void {
		$subject = $this->artwork( label: null );

		$this->assertSame(
			self::TITLE,
			$this->newNamer()->displayName( $subject, new PageSubjects( null, new SubjectMap( $subject ) ), 'Page name' )
		);
	}

	public function testDisplayNameOnPageReadsTheTemplateLabel(): void {
		$subject = $this->artwork( label: null );

		$this->assertSame(
			self::TITLE,
			$this->newNamer()->displayNameOnPage(
				$subject,
				TestPage::build( properties: TestPageProperties::build( title: 'Page name' ), mainSubject: $subject )
			)
		);
	}

	public function testDisplayNameWithoutPageReadsTheTemplateLabel(): void {
		$this->assertSame( self::TITLE, $this->newNamer()->displayNameWithoutPage( $this->artwork( label: null ) ) );
	}

}
