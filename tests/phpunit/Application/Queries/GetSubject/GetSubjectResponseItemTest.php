<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetSubject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem
 */
class GetSubjectResponseItemTest extends TestCase {

	public function testFromSubjectCopiesSubjectIdentity(): void {
		$item = GetSubjectResponseItem::fromSubject(
			TestSubject::build(
				id: 's1demo1aaaaaaa1',
				label: new SubjectLabel( 'ACME Corp' ),
				schemaName: new SchemaName( 'Organization' ),
			),
			null
		);

		$this->assertSame( 's1demo1aaaaaaa1', $item->id );
		$this->assertSame( 'ACME Corp', $item->label );
		$this->assertSame( 'Organization', $item->schema );
	}

	public function testFromSubjectArrayifiesStatementsByPropertyName(): void {
		$item = GetSubjectResponseItem::fromSubject(
			TestSubject::build(
				statements: new StatementList( [
					TestStatement::build( property: 'Animal', value: 'bunny' ),
					TestStatement::build( property: 'Fluff', value: new NumberValue( 9001 ), propertyType: 'number' ),
				] )
			),
			null
		);

		$this->assertSame(
			[
				'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ],
				'Fluff' => [ 'propertyType' => 'number', 'value' => 9001 ],
			],
			$item->statements
		);
	}

	public function testFromSubjectWithoutPageIdentifiersLeavesPageFieldsNull(): void {
		$item = GetSubjectResponseItem::fromSubject( TestSubject::build(), null );

		$this->assertNull( $item->pageId );
		$this->assertNull( $item->pageTitle );
		$this->assertNull( $item->pageNamespaceId );
	}

	public function testFromSubjectCopiesPageIdentifiers(): void {
		$item = GetSubjectResponseItem::fromSubject(
			TestSubject::build(),
			new PageIdentifiers( new PageId( 42 ), 'Help:Bunnies', 12 )
		);

		$this->assertSame( 42, $item->pageId );
		$this->assertSame( 'Help:Bunnies', $item->pageTitle );
		$this->assertSame( 12, $item->pageNamespaceId );
	}

}
