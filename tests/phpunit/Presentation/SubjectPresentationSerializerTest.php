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
			displayName: 'ACME Corp',
			displayNameIsGenerated: false,
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
				'displayName' => 'ACME Corp',
				'displayNameIsGenerated' => false,
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
				'displayName' => 'ACME Corp',
				'displayNameIsGenerated' => false,
				'schema' => 'Organization',
				'statements' => [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ],
			],
			( new SubjectPresentationSerializer() )->serialize( $this->newItem( null ) )
		);
	}

	/**
	 * A display name that fell back to the Schema name is reported as generated, so a client can say
	 * so instead of presenting it as a name someone wrote. It cannot tell from the name itself.
	 */
	public function testSerializesAnAbsentLabelAsNullBesideTheDisplayName(): void {
		$item = new GetSubjectResponseItem(
			id: 's1demo1aaaaaaa1',
			label: null,
			displayName: 'Organization',
			displayNameIsGenerated: true,
			schemaName: 'Organization',
			statements: [],
			pageId: null,
			pageTitle: null,
			pageNamespaceId: null,
		);

		$this->assertSame(
			[
				'id' => 's1demo1aaaaaaa1',
				'label' => null,
				'displayName' => 'Organization',
				'displayNameIsGenerated' => true,
				'schema' => 'Organization',
				'statements' => [],
			],
			( new SubjectPresentationSerializer() )->serialize( $item )
		);
	}

}
