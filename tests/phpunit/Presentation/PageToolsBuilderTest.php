<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Skin\SkinComponentUtils;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Presentation\PageToolsBuilder;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\PageToolsBuilder
 */
class PageToolsBuilderTest extends MediaWikiIntegrationTestCase {

	private const PAGE_NAME = 'PageToolsBuilderTestPage';

	public function testReturnsNoItemsOutsideContentNamespace(): void {
		$this->assertSame(
			[],
			$this->build( isContentNamespace: false )
		);
	}

	public function testShowsBothItemsWhenEverythingOpenAndDevUiEnabled(): void {
		$this->assertSame(
			[
				$this->createSubjectItem(),
				$this->editJsonItem(),
			],
			$this->build()
		);
	}

	public function testShowsOnlyCreateSubjectWhenDevUiDisabled(): void {
		$this->assertSame(
			[ $this->createSubjectItem() ],
			$this->build( devUiEnabled: false )
		);
	}

	public function testShowsOnlyEditJsonWhenPageHasSubjects(): void {
		$this->assertSame(
			[ $this->editJsonItem() ],
			$this->build( pageHasSubjects: true )
		);
	}

	public function testShowsOnlyEditJsonOnOldRevision(): void {
		$this->assertSame(
			[ $this->editJsonItem() ],
			$this->build( isLatestRevision: false )
		);
	}

	public function testShowsOnlyEditJsonWhenUserCannotCreateSubjects(): void {
		$this->assertSame(
			[ $this->editJsonItem() ],
			$this->build( canCreateMainSubject: false )
		);
	}

	public function testReturnsNoItemsWhenNothingQualifies(): void {
		$this->assertSame(
			[],
			$this->build(
				canCreateMainSubject: false,
				devUiEnabled: false
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function build(
		bool $isContentNamespace = true,
		bool $canCreateMainSubject = true,
		bool $isLatestRevision = true,
		bool $pageHasSubjects = false,
		bool $devUiEnabled = true
	): array {
		return ( new PageToolsBuilder() )->build(
			title: Title::newFromText( self::PAGE_NAME ),
			isContentNamespace: $isContentNamespace,
			canCreateMainSubject: $canCreateMainSubject,
			isLatestRevision: $isLatestRevision,
			pageHasSubjects: $pageHasSubjects,
			devUiEnabled: $devUiEnabled
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createSubjectItem(): array {
		return [
			'text' => wfMessage( 'neowiki-page-tools-create-subject' )->text(),
			'href' => '#',
			'id' => 't-neowiki-create-subject',
			'data' => [
				'mw-neowiki-action' => 'open-subject-creator',
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function editJsonItem(): array {
		return [
			'text' => wfMessage( 'neowiki-page-tools-edit-json' )->text(),
			'href' => SkinComponentUtils::makeSpecialUrlSubpage( 'NeoJson', self::PAGE_NAME ),
			'id' => 't-neowiki-edit-json',
		];
	}

}
