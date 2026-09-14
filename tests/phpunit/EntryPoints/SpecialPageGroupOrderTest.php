<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\SpecialSpecialPages;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onSpecialPageInitList
 * @group Database
 */
class SpecialPageGroupOrderTest extends SpecialPageTestBase {

	protected function newSpecialPage(): SpecialSpecialPages {
		return new SpecialSpecialPages();
	}

	public function testNeoWikiGroupIsListedFirst(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage();

		$this->assertSame(
			'neowiki',
			$this->firstGroupHeadingIn( $output ),
			'Without the reordering "maintenance" leads, as Special:BrokenRedirects heads the core list'
		);
	}

	public function testSpecialPageListStartsWithANeoWikiPage(): void {
		$factory = $this->specialPageFactory();
		$firstPage = $factory->getNames()[0];

		$this->assertSame(
			'neowiki',
			$factory->getPage( $firstPage )?->getFinalGroupName(),
			"$firstPage heads the special page list, and it is not a NeoWiki page"
		);
	}

	public function testReorderingKeepsTheCoreSpecialPages(): void {
		$this->assertContains(
			'Recentchanges',
			$this->specialPageFactory()->getNames(),
			'Moving the NeoWiki pages to the front must not drop the pages they overtake'
		);
	}

	private function specialPageFactory(): SpecialPageFactory {
		return $this->getServiceContainer()->getSpecialPageFactory();
	}

	/**
	 * The group of the first "(specialpages-group-<group>)" message in the qqx output.
	 */
	private function firstGroupHeadingIn( string $html ): ?string {
		preg_match( '/\(specialpages-group-([a-z0-9-]+)\)/', $html, $matches );

		return $matches[1] ?? null;
	}

}
