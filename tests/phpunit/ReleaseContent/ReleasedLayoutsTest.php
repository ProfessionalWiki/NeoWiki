<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\ReleaseContent;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Presentation\LayoutPresentationSerializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutPersistenceDeserializer
 */
class ReleasedLayoutsTest extends TestCase {

	use ReadsReleaseContent;

	/**
	 * @dataProvider layoutProvider
	 */
	public function testReadsEveryLayoutAsStored( string $release, string $layoutName ): void {
		$layout = ( new LayoutPersistenceDeserializer( NeoWikiExtension::getInstance()->getSchemaReferenceParser() ) )
			->deserialize( new LayoutName( $layoutName ), self::releaseContent( $release, 'Layout', $layoutName ) );

		$this->assertEquals(
			self::decodedReleaseContent( $release, 'Layout', $layoutName ),
			json_decode( ( new LayoutPresentationSerializer() )->serialize( $layout ), true )
		);
	}

	public static function layoutProvider(): iterable {
		return self::releaseContentPages( 'Layout' );
	}

}
