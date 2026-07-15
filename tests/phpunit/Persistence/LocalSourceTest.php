<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Persistence\LocalSource;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\LocalSource
 */
class LocalSourceTest extends TestCase {

	private const string BASE_URI = 'https://example.org/entity/';

	public function testGetSubjectReturnsTheSubjectFromTheLocalLookup(): void {
		$subject = TestSubject::build( id: 's11111111111111' );

		$source = $this->newLocalSource( subjectLookup: new InMemorySubjectLookup( $subject ) );

		$this->assertSame( $subject, $source->getSubject( new SubjectId( 's11111111111111' ) ) );
	}

	public function testGetSubjectReturnsNullWhenTheLocalLookupHasNoSuchSubject(): void {
		$source = $this->newLocalSource( subjectLookup: new InMemorySubjectLookup() );

		$this->assertNull( $source->getSubject( new SubjectId( 's11111111111111' ) ) );
	}

	public function testGetSchemaReturnsTheSchemaFromTheLocalLookup(): void {
		$schema = TestSchema::build( name: 'Person' );

		$source = $this->newLocalSource( schemaLookup: new InMemorySchemaLookup( $schema ) );

		$this->assertSame( $schema, $source->getSchema( new SchemaName( 'Person' ) ) );
	}

	public function testGetSchemaReturnsNullWhenTheLocalLookupHasNoSuchSchema(): void {
		$source = $this->newLocalSource( schemaLookup: new InMemorySchemaLookup() );

		$this->assertNull( $source->getSchema( new SchemaName( 'Person' ) ) );
	}

	public function testLocalSubjectsAreEditable(): void {
		$this->assertTrue( $this->newLocalSource()->isEditable() );
	}

	public function testBareNanoidIsAValidLocalId(): void {
		$this->assertTrue( $this->newLocalSource()->isValidLocalId( 's11111111111111' ) );
	}

	public function testSourceQualifiedIdIsNotAValidLocalId(): void {
		$this->assertFalse( $this->newLocalSource()->isValidLocalId( 'otherwiki:s11111111111111' ) );
	}

	public function testGarbageIsNotAValidLocalId(): void {
		$this->assertFalse( $this->newLocalSource()->isValidLocalId( 'not a subject id' ) );
	}

	public function testEmptyStringIsNotAValidLocalId(): void {
		$this->assertFalse( $this->newLocalSource()->isValidLocalId( '' ) );
	}

	public function testGetBaseUriReturnsTheConfiguredWikiBase(): void {
		$this->assertSame( self::BASE_URI, $this->newLocalSource()->getBaseUri() );
	}

	private function newLocalSource(
		?InMemorySubjectLookup $subjectLookup = null,
		?InMemorySchemaLookup $schemaLookup = null,
	): LocalSource {
		return new LocalSource(
			subjectLookup: $subjectLookup ?? new InMemorySubjectLookup(),
			schemaLookup: $schemaLookup ?? new InMemorySchemaLookup(),
			baseUri: self::BASE_URI,
		);
	}

}
