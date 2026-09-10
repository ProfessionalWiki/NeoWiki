<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\ValidateSubject;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ProfessionalWiki\NeoWiki\Application\Queries\ValidateSubject\ValidateSubjectQuery;
use ProfessionalWiki\NeoWiki\Application\Schema\Exception\SchemaNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\SelectValueResolver;
use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Infrastructure\ProductionIdGenerator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySource;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\ValidateSubject\ValidateSubjectQuery
 */
class ValidateSubjectQueryTest extends TestCase {

	private const string LOCAL_SCHEMA_NAME = 'Company';
	private const string SOURCED_SCHEMA_NAME = 'Widget';

	public function testValidatesAgainstASchemaOfThisWiki(): void {
		$violations = $this->newQuery()->validate( self::LOCAL_SCHEMA_NAME, [] );

		$this->assertSame( [], $violations );
	}

	public function testValidatesAgainstASchemaOfAnotherSource(): void {
		$violations = $this->newQuery()->validate(
			[ 'source' => 'catalog', 'name' => self::SOURCED_SCHEMA_NAME ],
			[]
		);

		$this->assertSame( [], $violations );
	}

	public function testReportsASchemaNoSourceOffers(): void {
		$this->expectException( SchemaNotFoundException::class );

		$this->newQuery()->validate( [ 'source' => 'catalog', 'name' => 'NoSuchSchema' ], [] );
	}

	public function testReportsAReferenceToAnUnregisteredSource(): void {
		$this->expectException( SchemaNotFoundException::class );

		$this->newQuery()->validate(
			[ 'source' => 'neverinstalled', 'name' => self::SOURCED_SCHEMA_NAME ],
			[]
		);
	}

	private function newQuery(): ValidateSubjectQuery {
		return new ValidateSubjectQuery(
			schemaResolver: $this->newSchemaResolver(),
			subjectValidator: new SubjectValidator(
				propertyTypeLookup: PropertyTypeRegistry::withCoreTypes( TestSubjectIds::LOCAL_SOURCE_KEY ),
				subjectLookup: new InMemorySubjectLookup(),
				sourceRegistry: TestSources::newRegistry(),
			),
			statementListBuilder: new StatementListBuilder(
				PropertyTypeRegistry::withCoreTypes( TestSubjectIds::LOCAL_SOURCE_KEY ),
				new ProductionIdGenerator(),
				TestSubjectIds::newParser(),
			),
			selectStatementResolver: new SelectStatementResolver( new SelectValueResolver() ),
			localSourceKey: TestSubjectIds::LOCAL_SOURCE_KEY,
		);
	}

	private function newSchemaResolver(): SchemaResolver {
		$catalog = new InMemorySource();
		$catalog->addSchema( TestSchema::build( name: self::SOURCED_SCHEMA_NAME ) );

		$registry = TestSources::newRegistryWithLocalSchemas(
			new InMemorySchemaLookup( TestSchema::build( name: self::LOCAL_SCHEMA_NAME ) )
		);
		$registry->registerSource( 'catalog', $catalog );

		return new SchemaResolver( $registry, new NullLogger() );
	}

}
