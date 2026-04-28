<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\RedHerb;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
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
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setOutput( new OutputPage( $context ) );
		$page->setContext( $context );
		$page->execute( null );

		$modules = $context->getOutput()->getModules();

		$this->assertContains( 'ext.neowiki', $modules );
		$this->assertContains( 'ext.redherb-subject-finder', $modules );
	}

}
