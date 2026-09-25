<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\LabelTemplateRenderer;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Value\MonolingualText;
use ProfessionalWiki\NeoWiki\Domain\Value\MonolingualTextValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\LabelTemplateRenderer
 */
class LabelTemplateRendererTest extends TestCase {

	private const string SCHEMA_NAME = 'Artwork';

	public function testLabelIsTheTextValueTheTemplateNames(): void {
		$this->assertSame(
			'Madonna and Child on a Cloud',
			$this->render( '{Title}', TestStatement::build( property: 'Title', value: 'Madonna and Child on a Cloud' ) )
		);
	}

	private function render( string $template, Statement ...$statements ): ?string {
		return $this->newRenderer()->render( $this->subjectWith( ...$statements ), $this->schemaWithTemplate( $template ) );
	}

	private function newRenderer(): LabelTemplateRenderer {
		return new LabelTemplateRenderer( TestSources::newPropertyTypeRegistry() );
	}

	private function subjectWith( Statement ...$statements ): Subject {
		return TestSubject::build( label: null, schemaName: new SchemaName( self::SCHEMA_NAME ), statements: new StatementList( $statements ) );
	}

	private function schemaWithTemplate( ?string $template ): Schema {
		return TestSchema::build(
			name: self::SCHEMA_NAME,
			properties: new PropertyDefinitions( [
				'Title' => TestProperty::buildText(),
				'Year' => TestProperty::buildNumber(),
				'Status' => TestProperty::buildSelect( new SelectOption( 'o1', 'On loan' ), new SelectOption( 'o2', 'In storage' ) ),
			] ),
			labelTemplate: $template,
		);
	}

	public function testFirstOfSeveralValuesIsUsed(): void {
		$this->assertSame(
			'Madonna and Child on a Cloud',
			$this->render(
				'{Title}',
				TestStatement::build( property: 'Title', value: new StringValue( 'Madonna and Child on a Cloud', 'Madonna på molnet' ) )
			)
		);
	}

	public function testNumberIsWrittenOut(): void {
		$this->assertSame(
			'Rijksmuseum 2024',
			$this->render( 'Rijksmuseum {Year}', TestStatement::build( property: 'Year', value: new NumberValue( 2024 ), propertyType: 'number' ) )
		);
	}

	public function testMonolingualTextReadsAsItsFirstTextWithoutItsLanguage(): void {
		$this->assertSame(
			'Madonna på molnet',
			$this->render(
				'{Title}',
				TestStatement::build(
					property: 'Title',
					value: new MonolingualTextValue(
						new MonolingualText( 'Madonna på molnet', 'sv' ),
						new MonolingualText( 'Madonna and Child on a Cloud', 'en' )
					),
					propertyType: 'monolingualText'
				)
			)
		);
	}

	public function testSelectValueReadsAsItsOptionLabel(): void {
		$this->assertSame(
			'In storage',
			$this->render( '{Status}', TestStatement::build( property: 'Status', value: 'o2', propertyType: 'select' ) )
		);
	}

	public function testNoLabelWhenTheSubjectLacksEveryValueTheTemplateNames(): void {
		$this->assertNull( $this->render( '{Title}', TestStatement::build( property: 'Year', value: new NumberValue( 2024 ), propertyType: 'number' ) ) );
	}

	public function testValueOfATypeTheWikiDoesNotHaveReadsAsNothing(): void {
		$this->assertNull( $this->render( '{Title}', TestStatement::build( property: 'Title', value: 'Madonna', propertyType: 'color' ) ) );
	}

	/**
	 * A Schema from an import or another Source need not have passed save-time validation.
	 */
	public function testValueOfAPropertyTheSchemaLacksStillReads(): void {
		$this->assertSame( 'Madonna', $this->render( '{Former title}', TestStatement::build( property: 'Former title', value: 'Madonna' ) ) );
	}

	public function testNoLabelWhenTheSchemaHasNoTemplate(): void {
		$this->assertNull(
			$this->newRenderer()->render(
				$this->subjectWith( TestStatement::build( property: 'Title', value: 'Madonna and Child on a Cloud' ) ),
				$this->schemaWithTemplate( null )
			)
		);
	}

}
