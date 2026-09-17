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

	/**
	 * The reference the caller sends is read as a Subject's own is, so validating against the Schema
	 * a spelling names agrees with validating the Subject that ends up stored under it.
	 */
	public function testValidatesAgainstTheSchemaAnotherSpellingNames(): void {
		$violations = $this->newQuery( [ 'company' => self::LOCAL_SCHEMA_NAME ] )->validate( 'company', [] );

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

	/**
	 * @param array<string, string> $schemaNames Name as written => the name of the Schema it names.
	 */
	private function newQuery( array $schemaNames = [] ): ValidateSubjectQuery {
		return new ValidateSubjectQuery(
			schemaResolver: $this->newSchemaResolver(),
			subjectValidator: new SubjectValidator(
				propertyTypeLookup: TestSources::newPropertyTypeRegistry(),
				subjectLookup: new InMemorySubjectLookup(),
				sourceRegistry: TestSources::newRegistry(),
			),
			statementListBuilder: new StatementListBuilder(
				TestSources::newPropertyTypeRegistry(),
				new ProductionIdGenerator(),
				TestSubjectIds::newParser(),
			),
			selectStatementResolver: new SelectStatementResolver( new SelectValueResolver() ),
			schemaReferenceParser: TestSources::newSchemaReferenceParser( $schemaNames ),
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
