<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Presentation\SubjectPresentationSerializer;
use stdClass;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\SubjectPresentationSerializer
 */
class SubjectPresentationSerializerTest extends TestCase {

	/**
	 * @param array<string, mixed> $statements
	 */
	private function newItem(
		?int $pageId,
		?string $pageTitle = null,
		?int $pageNamespaceId = null,
		array $statements = [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ]
	): GetSubjectResponseItem {
		return new GetSubjectResponseItem(
			id: 's1demo1aaaaaaa1',
			label: 'ACME Corp',
			displayName: 'ACME Corp',
			displayNameIsGenerated: false,
			schema: 'Organization',
			statements: $statements,
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

	public function testSerializesNoStatementsAsAnEmptyObject(): void {
		// A Subject keyed by property name does not become a list when it holds no Statements: an
		// empty PHP array encodes as `[]`, which is not the object the Subject format specifies.
		$json = (string)json_encode(
			( new SubjectPresentationSerializer() )->serialize( $this->newItem( null, statements: [] ) )
		);

		$this->assertStringContainsString( '"statements":{}', $json );
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
			schema: 'Organization',
			statements: [],
			pageId: null,
			pageTitle: null,
			pageNamespaceId: null,
		);

		$this->assertEquals(
			[
				'id' => 's1demo1aaaaaaa1',
				'label' => null,
				'displayName' => 'Organization',
				'displayNameIsGenerated' => true,
				'schema' => 'Organization',
				'statements' => new stdClass(),
			],
			( new SubjectPresentationSerializer() )->serialize( $item )
		);
	}

}
