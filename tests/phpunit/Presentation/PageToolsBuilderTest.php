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

	public function testShowsAllItemsWhenEverythingOpenAndDevUiEnabled(): void {
		$this->assertSame(
			[
				$this->createSubjectItem(),
				$this->manageSubjectsItem(),
				$this->editJsonItem(),
			],
			$this->build()
		);
	}

	public function testShowsCreateAndManageWhenDevUiDisabled(): void {
		$this->assertSame(
			[ $this->createSubjectItem(), $this->manageSubjectsItem() ],
			$this->build( devUiEnabled: false )
		);
	}

	public function testShowsManageAndEditJsonOnOldRevision(): void {
		$this->assertSame(
			[ $this->manageSubjectsItem(), $this->editJsonItem() ],
			$this->build( isLatestRevision: false )
		);
	}

	public function testShowsManageAndEditJsonWhenUserCannotCreateSubjects(): void {
		$this->assertSame(
			[ $this->manageSubjectsItem(), $this->editJsonItem() ],
			$this->build( canCreateMainSubject: false )
		);
	}

	public function testReturnsOnlyManageSubjectsWhenNothingElseQualifies(): void {
		$this->assertSame(
			[ $this->manageSubjectsItem() ],
			$this->build(
				canCreateMainSubject: false,
				devUiEnabled: false
			)
		);
	}

	public function testHidesManageSubjectsLinkWhenAlreadyViewingSubjectsAction(): void {
		$this->assertSame(
			[ $this->createSubjectItem(), $this->editJsonItem() ],
			$this->build( currentAction: 'subjects' )
		);
	}

	public function testReturnsEmptyListOnSubjectsActionWhenNothingElseQualifies(): void {
		$this->assertSame(
			[],
			$this->build(
				canCreateMainSubject: false,
				devUiEnabled: false,
				currentAction: 'subjects'
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
		bool $devUiEnabled = true,
		string $currentAction = 'view'
	): array {
		return ( new PageToolsBuilder() )->build(
			title: Title::newFromText( self::PAGE_NAME ),
			isContentNamespace: $isContentNamespace,
			canCreateMainSubject: $canCreateMainSubject,
			isLatestRevision: $isLatestRevision,
			devUiEnabled: $devUiEnabled,
			currentAction: $currentAction
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
	private function manageSubjectsItem(): array {
		return [
			'text' => wfMessage( 'neowiki-page-tools-manage-subjects' )->text(),
			'href' => Title::newFromText( self::PAGE_NAME )->getLocalURL( [ 'action' => 'subjects' ] ),
			'id' => 't-neowiki-manage-subjects',
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
