<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Cypher;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Cypher
 */
class CypherTest extends TestCase {

	public function testSafeValuesAreNotEscaped(): void {
		$this->assertSame( 'hello', Cypher::escape( 'hello' ) );
		$this->assertSame( 'hello_world', Cypher::escape( 'hello_world' ) );
	}

	public function testUnsafeValuesAreNotEscaped(): void {
		$this->assertSame( '`_`', Cypher::escape( '_' ) );
		$this->assertSame( '`__`', Cypher::escape( '__' ) );
		$this->assertSame( "`'`", Cypher::escape( "'" ) );
		$this->assertSame( '`"`', Cypher::escape( '"' ) );
		$this->assertSame( '`0`', Cypher::escape( '0' ) );
		$this->assertSame( '`1`', Cypher::escape( '1' ) );
		$this->assertSame( '`1337`', Cypher::escape( '1337' ) );
		$this->assertSame( '`Evil```', Cypher::escape( 'Evil`' ) );
		$this->assertSame( '`a``b`', Cypher::escape( 'a`b' ) );
		$this->assertSame( '`a:b`', Cypher::escape( 'a:b' ) );
		$this->assertSame( '`a-b`', Cypher::escape( 'a-b' ) );
	}

	public function testEscapeThrowsExceptionOnEmptyString(): void {
		$this->expectException( InvalidArgumentException::class );
		Cypher::escape( '' );
	}

	public function testNameHoldingABackslashIsRefused(): void {
		$this->expectException( InvalidArgumentException::class );
		Cypher::escape( 'a' . chr( 92 ) . 'b' );
	}

}
