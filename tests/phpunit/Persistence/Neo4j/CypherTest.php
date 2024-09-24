<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki\Persistence\Neo4j;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\Neo4j\Cypher;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Persistence\Neo4j\Cypher
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

	public function testBuildLabelList(): void {
		$labels = [ 'Label1', 'Label2', 'Label3' ];
		$expected = 'Label1:Label2:Label3';
		$this->assertSame( $expected, Cypher::buildLabelList( $labels ) );
	}

	public function testBuildLabelListWithEmptyArray(): void {
		$this->assertSame( '', Cypher::buildLabelList( [] ) );
	}

	public function testBuildLabelListEscapes(): void {
		$labels = [ '_', 'Evil`', 'Label3' ];
		$expected = '`_`:`Evil```:Label3';
		$this->assertSame( $expected, Cypher::buildLabelList( $labels ) );
	}

	public function testBuildLabelListWithInvalidLabel(): void {
		$this->expectException( InvalidArgumentException::class );
		Cypher::buildLabelList( [ 'ValidLabel', '' ] );
	}

}
