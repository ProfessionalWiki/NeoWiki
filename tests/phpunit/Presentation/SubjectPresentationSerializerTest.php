<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Presentation\SubjectPresentationSerializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\SubjectPresentationSerializer
 */
class SubjectPresentationSerializerTest extends TestCase {

	private function newItem( ?int $pageId, ?string $pageTitle = null, ?int $pageNamespaceId = null ): GetSubjectResponseItem {
		return new GetSubjectResponseItem(
			id: 's1demo1aaaaaaa1',
			label: 'ACME Corp',
			schemaName: 'Organization',
			statements: [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ],
			pageId: $pageId,
			pageTitle: $pageTitle,
			pageNamespaceId: $pageNamespaceId,
		);
	}

	public function testSerializesPageIdentifiersBetweenSchemaAndStatements(): void {
		$this->assertSame(
			[
				'id' => 's1demo1aaaaaaa1',
				'label' => 'ACME Corp',
				'schema' => 'Organization',
				'pageId' => 42,
				'pageTitle' => 'Help:Bunnies',
				'pageNamespaceId' => 12,
				'statements' => [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ],
			],
			( new SubjectPresentationSerializer() )->serialize( $this->newItem( 42, 'Help:Bunnies', 12 ) )
		);
	}

	public function testOmitsPageKeysWhenThePageIsUnresolved(): void {
		$this->assertSame(
			[
				'id' => 's1demo1aaaaaaa1',
				'label' => 'ACME Corp',
				'schema' => 'Organization',
				'statements' => [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ],
			],
			( new SubjectPresentationSerializer() )->serialize( $this->newItem( null ) )
		);
	}

}
