<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageDateTime;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;
use ProfessionalWiki\NeoWiki\Persistence\CorePagePropertyProvider;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\CorePagePropertyProvider
 */
class CorePagePropertyProviderTest extends TestCase {

	public function testReturnsExpectedKeys(): void {
		$properties = $this->getPropertiesForContext();

		$this->assertSame(
			[ 'name', 'creationTime', 'lastUpdated', 'categories', 'lastEditor' ],
			array_keys( $properties )
		);
	}

	public function testNameMatchesPageTitle(): void {
		$properties = $this->getPropertiesForContext( pageTitle: 'Test Page' );

		$this->assertSame( 'Test Page', $properties['name'] );
	}

	public function testCreationTimeIsPageDateTime(): void {
		$properties = $this->getPropertiesForContext( creationTime: '20230726163439' );

		$this->assertInstanceOf( PageDateTime::class, $properties['creationTime'] );
		$this->assertSame( '20230726163439', $properties['creationTime']->timestamp );
	}

	public function testLastUpdatedIsPageDateTime(): void {
		$properties = $this->getPropertiesForContext( modificationTime: '20240315100000' );

		$this->assertInstanceOf( PageDateTime::class, $properties['lastUpdated'] );
		$this->assertSame( '20240315100000', $properties['lastUpdated']->timestamp );
	}

	public function testCategoriesMatchContext(): void {
		$properties = $this->getPropertiesForContext( categories: [ 'CatA', 'CatB' ] );

		$this->assertSame( [ 'CatA', 'CatB' ], $properties['categories'] );
	}

	public function testLastEditorMatchesContext(): void {
		$properties = $this->getPropertiesForContext( lastEditor: 'JohnDoe' );

		$this->assertSame( 'JohnDoe', $properties['lastEditor'] );
	}

	/**
	 * @param string[] $categories
	 */
	private function getPropertiesForContext(
		string $pageTitle = 'Default Title',
		string $creationTime = '20230101000000',
		string $modificationTime = '20230101000000',
		array $categories = [],
		string $lastEditor = 'DefaultEditor',
	): array {
		return ( new CorePagePropertyProvider() )->getProperties(
			new PagePropertyProviderContext(
				pageId: new PageId( 1 ),
				pageTitle: $pageTitle,
				creationTime: $creationTime,
				modificationTime: $modificationTime,
				categories: $categories,
				lastEditor: $lastEditor,
			)
		);
	}

}
