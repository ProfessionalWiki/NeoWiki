<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SourceRoutingSchemaReferenceResolver;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyLogger;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubSource;
use Psr\Log\LogLevel;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\SourceRoutingSchemaReferenceResolver
 */
class SourceRoutingSchemaReferenceResolverTest extends TestCase {

	private const string LOCAL_KEY = 'localwiki';

	public function testResolvesLocalReferenceThroughTheLocalSource(): void {
		$schema = $this->newSchema( 'Person' );

		$result = $this->newResolver( localSource: new StubSource( schema: $schema ) )
			->resolve( SchemaReference::local( new SchemaName( 'Person' ) ) );

		$this->assertSame( $schema, $result );
	}

	public function testResolvesForeignReferenceThroughItsSource(): void {
		$foreignSchema = $this->newSchema( 'Person' );
		$registry = new SourceRegistry( self::LOCAL_KEY );
		$registry->register( self::LOCAL_KEY, new StubSource( schema: $this->newSchema( 'Local' ) ) );
		$registry->register( 'otherwiki', new StubSource( schema: $foreignSchema ) );

		$result = ( new SourceRoutingSchemaReferenceResolver( $registry, new SpyLogger() ) )
			->resolve( new SchemaReference( 'otherwiki', new SchemaName( 'Person' ) ) );

		$this->assertSame( $foreignSchema, $result );
	}

	public function testUnknownSourceReturnsNull(): void {
		$result = $this->newResolver( localSource: new StubSource() )
			->resolve( new SchemaReference( 'unknownwiki', new SchemaName( 'Person' ) ) );

		$this->assertNull( $result );
	}

	public function testUnknownSourceLogsOneDiagnosticWarning(): void {
		$logger = new SpyLogger();

		$this->newResolver( localSource: new StubSource(), logger: $logger )
			->resolve( new SchemaReference( 'unknownwiki', new SchemaName( 'Person' ) ) );

		$this->assertCount( 1, $logger->getLogCalls() );
		$this->assertSame( LogLevel::WARNING, $logger->getLogCalls()[0]['level'] );
		$this->assertSame( 'unknownwiki', $logger->getLogCalls()[0]['context']['sourceKey'] );
		$this->assertSame( 'Person', $logger->getLogCalls()[0]['context']['schemaName'] );
	}

	public function testKnownSourceWithMissingSchemaReturnsNullWithoutLogging(): void {
		$logger = new SpyLogger();

		$result = $this->newResolver( localSource: new StubSource( schema: null ), logger: $logger )
			->resolve( SchemaReference::local( new SchemaName( 'Person' ) ) );

		$this->assertNull( $result );
		$this->assertSame( [], $logger->getLogCalls() );
	}

	private function newResolver( StubSource $localSource, ?SpyLogger $logger = null ): SourceRoutingSchemaReferenceResolver {
		$registry = new SourceRegistry( self::LOCAL_KEY );
		$registry->register( 'otherwiki', new StubSource( schema: $this->newSchema( 'Other' ) ) );
		$registry->register( self::LOCAL_KEY, $localSource );

		return new SourceRoutingSchemaReferenceResolver( $registry, $logger ?? new SpyLogger() );
	}

	private function newSchema( string $name ): Schema {
		return new Schema( new SchemaName( $name ), '', new PropertyDefinitions( [] ) );
	}

}
