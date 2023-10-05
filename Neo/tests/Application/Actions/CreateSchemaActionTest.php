<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use MediaWiki\MediaWikiServices;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSchema\CreateSchemaAction;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSchema\CreateSchemaPresenter;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormatRegistry;
use ProfessionalWiki\NeoWiki\Infrastructure\SchemaAuthorizer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSchemaAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\CreateSchema\CreateSchemaAction
 */
class CreateSchemaActionTest extends TestCase {

	use MockAuthorityTrait;

	private CreateSchemaPresenter $presenter;
	private PageContentSaver $pageContentSaver;
	private SchemaLookup $schemaLookup;
	private SchemaPersistenceDeserializer $persistenceDeserializer;
	private SchemaAuthorizer $authorizer;

	public function setUp(): void {
		$this->presenter = new CreateSchemaPresenterSpy();
		$this->pageContentSaver = new PageContentSaver(
			wikiPageFactory: MediaWikiServices::getInstance()->getWikiPageFactory(),
			performer: $this->mockRegisteredUltimateAuthority(),
		);
		$this->schemaLookup = new InMemorySchemaLookup();
		$this->persistenceDeserializer = new SchemaPersistenceDeserializer(
			new ValueFormatRegistry()
		);
		$this->authorizer = new SucceedingSchemaAuthorizer();
	}

	public function testCannotCreateSchemaWithReservedName(): void {
		$action = $this->newCreateSchemaAction();

		$action->execute( 'Page', $this->createMinimalSchemaJson() );

		$this->assertTrue( $this->presenter->presentedInvalidTitle );
	}

	private function newCreateSchemaAction(): CreateSchemaAction {
		return new CreateSchemaAction(
			$this->presenter,
			$this->pageContentSaver,
			$this->schemaLookup,
			$this->persistenceDeserializer,
			$this->authorizer
		);
	}

	private function createMinimalSchemaJson(): string {
		return <<<JSON
		{
			"propertyDefinitions": {}
		}
JSON;
	}

}
