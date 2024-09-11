<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

#[CoversClass( SchemaName::class )]
class SchemaNameTest extends TestCase {

	public function testNonReservedSchemaNameIsValid(): void {
		$schemaName = new SchemaName( 'FooBar' );

		$this->assertSame( 'FooBar', $schemaName->getText() );
	}

	public function testEmptySchemaNameIsInvalid(): void {
		$this->expectException( InvalidArgumentException::class );

		new SchemaName( '' );
	}

	#[DataProvider( 'reservedSchemaNameProvider' )]
	public function testReservedSchemaNameIsInvalid( string $name ): void {
		$this->expectException( InvalidArgumentException::class );

		new SchemaName( $name );
	}

	public static function reservedSchemaNameProvider(): iterable {
		yield [ 'Page' ];
		yield [ 'Subject' ];
		yield [ 'pAgE' ];
		yield [ 'sUbJeCt' ];
	}

}
