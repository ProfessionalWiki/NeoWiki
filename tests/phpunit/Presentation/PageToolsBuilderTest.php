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
	private const PAGE_ID = 42;

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
				$this->rdfItem(),
				$this->editJsonItem(),
			],
			$this->build( hasSubjects: true )
		);
	}

	public function testShowsRdfLinkWhenPageHasSubjects(): void {
		$this->assertSame(
			[ $this->manageSubjectsItem(), $this->rdfItem() ],
			$this->build(
				hasSubjects: true,
				canCreateMainSubject: false,
				devUiEnabled: false
			)
		);
	}

	public function testHidesRdfLinkWhenPageHasNoSubjects(): void {
		$this->assertSame(
			[ $this->manageSubjectsItem() ],
			$this->build(
				hasSubjects: false,
				canCreateMainSubject: false,
				devUiEnabled: false
			)
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

	public function testUsesViewLabelsWhenUserCannotEditSubjects(): void {
		$this->assertSame(
			[
				$this->manageSubjectsItem( 'neowiki-page-tools-view-subjects' ),
				$this->editJsonItem( 'neowiki-page-tools-view-json' ),
			],
			$this->build(
				canCreateMainSubject: false,
				canEditSubject: false
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function build(
		bool $isContentNamespace = true,
		bool $hasSubjects = false,
		bool $canCreateMainSubject = true,
		bool $canEditSubject = true,
		bool $isLatestRevision = true,
		bool $devUiEnabled = true,
		string $currentAction = 'view'
	): array {
		return ( new PageToolsBuilder() )->build(
			title: Title::newFromText( self::PAGE_NAME ),
			pageId: self::PAGE_ID,
			isContentNamespace: $isContentNamespace,
			hasSubjects: $hasSubjects,
			canCreateMainSubject: $canCreateMainSubject,
			canEditSubject: $canEditSubject,
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
	private function manageSubjectsItem( string $messageKey = 'neowiki-page-tools-manage-subjects' ): array {
		return [
			'text' => wfMessage( $messageKey )->text(),
			'href' => Title::newFromText( self::PAGE_NAME )->getLocalURL( [ 'action' => 'subjects' ] ),
			'id' => 't-neowiki-manage-subjects',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function rdfItem(): array {
		return [
			'text' => wfMessage( 'neowiki-page-tools-rdf' )->text(),
			'href' => wfScript( 'rest' ) . '/neowiki/v0/page/'
				. self::PAGE_ID . '/rdf?format=turtle',
			'id' => 't-neowiki-rdf',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function editJsonItem( string $messageKey = 'neowiki-page-tools-edit-json' ): array {
		return [
			'text' => wfMessage( $messageKey )->text(),
			'href' => SkinComponentUtils::makeSpecialUrlSubpage( 'NeoJson', self::PAGE_NAME ),
			'id' => 't-neowiki-edit-json',
		];
	}

}
