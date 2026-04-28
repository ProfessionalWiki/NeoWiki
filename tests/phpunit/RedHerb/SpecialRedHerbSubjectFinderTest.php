<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\RedHerb;

use ProfessionalWiki\RedHerb\Specials\SpecialRedHerbSubjectFinder;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\RedHerb\Specials\SpecialRedHerbSubjectFinder
 */
class SpecialRedHerbSubjectFinderTest extends SpecialPageTestBase {

	protected function newSpecialPage(): SpecialRedHerbSubjectFinder {
		return new SpecialRedHerbSubjectFinder();
	}

	public function testOutputContainsRedHerbMountPoint(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage();

		$this->assertStringContainsString( 'id="ext-redherb-subject-finder"', $output );
	}

	public function testRegistersResourceLoaderModules(): void {
		$page = $this->newSpecialPage();
		$page->execute( null );

		$modules = $page->getOutput()->getModules();

		$this->assertContains( 'ext.neowiki', $modules );
		$this->assertContains( 'ext.redherb-subject-finder', $modules );
	}

}
