<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;

/**
 * Needs the database for the page revisions. Registering a policy rebuilds the extension singleton but
 * keeps MediaWiki's shared object cache, as a new request would.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PublishedPageJsonLookup
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageMappingLookup
 * @group Database
 */
class ConfigurationPageApprovalTest extends NeoWikiIntegrationTestCase {

	public function testServesThePreviousSchemaRevisionOnceApprovalOfTheLatestIsWithdrawn(): void {
		$previous = $this->createSchema( 'Approval', '{"description":"Previous","propertyDefinitions":{}}' );
		$latest = $this->createSchema( 'Approval', '{"description":"Latest","propertyDefinitions":{}}' );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $latest ) );
		$this->readSchemaDescription();

		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $previous ) );

		$this->assertSame( 'Previous', $this->readSchemaDescription() );
	}

	public function testServesThePreviousMappingRevisionOnceApprovalOfTheLatestIsWithdrawn(): void {
		$previous = $this->createMapping( 'Approval', $this->newMappingJson( 'edm:Agent' ) );
		$latest = $this->createMapping( 'Approval', $this->newMappingJson( 'edm:ProvidedCHO' ) );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $latest ) );
		$this->readMappedClass();

		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $previous ) );

		$this->assertSame( 'edm:Agent', $this->readMappedClass() );
	}

	private function readSchemaDescription(): ?string {
		return NeoWikiExtension::getInstance()->getSchemaLookup()
			->getSchema( new SchemaName( 'Approval' ) )
			?->getDescription();
	}

	private function readMappedClass(): ?string {
		return NeoWikiExtension::getInstance()->getMappingLookup()
			->getMapping( new MappingName( 'Approval' ) )
			?->forSchema( new SchemaName( 'Person' ) )
			?->subject
			?->class;
	}

	private function newMappingJson( string $personClass ): string {
		return json_encode( [
			'version' => 1,
			'prefixes' => [ 'edm' => 'http://www.europeana.eu/schemas/edm/' ],
			'schemas' => [
				'Person' => [ 'subject' => [ 'class' => $personClass ], 'properties' => (object)[] ],
			],
		] );
	}

}
