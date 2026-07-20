<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Actions;

use Article;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\Actions\SubjectsAction;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Actions\SubjectsAction
 * @group Database
 */
class SubjectsActionTest extends NeoWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

	public function testNullTitleIsNotEligible(): void {
		$this->assertFalse( SubjectsAction::isEligibleTitle( null ) );
	}

	public function testNonExistentTitleIsNotEligible(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( 0 );

		$this->assertFalse( SubjectsAction::isEligibleTitle( $title ) );
	}

	public function testTitleInNonContentNamespaceIsNotEligible(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( 1 );
		$title->method( 'getNamespace' )->willReturn( NS_USER_TALK );

		$this->assertFalse( SubjectsAction::isEligibleTitle( $title ) );
	}

	public function testTitleInContentNamespaceIsEligible(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( 1 );
		$title->method( 'getNamespace' )->willReturn( NS_MAIN );

		$this->assertTrue( SubjectsAction::isEligibleTitle( $title ) );
	}

	public function testExposesReadableRdfProjectionsAsConfigVar(): void {
		$this->createMapping( 'EDM', '{ "version": 1, "schemas": {} }' );

		$out = $this->runOnView( 'SubjectsActionTest projections', $this->getTestSysop()->getAuthority() );

		$this->assertSame(
			[ 'native', 'EDM' ],
			$out->getJsConfigVars()['wgNeoWikiRdfProjections']
		);
	}

	public function testOmitsRdfProjectionsTheViewingUserCannotRead(): void {
		$this->createMapping( 'EDM', '{ "version": 1, "schemas": {} }' );

		$out = $this->runOnView(
			'SubjectsActionTest restricted',
			$this->authorityWithGlobalReadButNoPageRead()
		);

		$this->assertSame(
			[ 'native' ],
			$out->getJsConfigVars()['wgNeoWikiRdfProjections'],
			'A read-restricted Mapping page name must not reach a reader who cannot see it.'
		);
	}

	public function testExposesTheSubjectIriBaseAsConfigVar(): void {
		$this->overrideConfigValue( 'NeoWikiRdfBaseUri', 'https://data.example.org' );
		NeoWikiExtension::resetInstance();

		try {
			$out = $this->runOnView( 'SubjectsActionTest iri base', $this->getTestSysop()->getAuthority() );

			$this->assertSame(
				'https://data.example.org/entity/',
				$out->getJsConfigVars()['wgNeoWikiSubjectIriBase']
			);
		} finally {
			NeoWikiExtension::resetInstance();
		}
	}

	private function runOnView( string $pageName, Authority $authority ): OutputPage {
		$title = $this->getExistingTestPage( $pageName )->getTitle();

		$context = new RequestContext();
		$context->setTitle( $title );
		$context->setAuthority( $authority );

		( new SubjectsAction( Article::newFromTitle( $title, $context ), $context ) )->onView();

		return $context->getOutput();
	}

}
