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

	public function testShowsAllItemsWhenEverythingOpenAndDevUiEnabled(): void {
		$this->assertEquals(
			[
				$this->createSubjectItem(),
				$this->rdfItem(),
				$this->editJsonItem(),
			],
			$this->build()
		);
	}

	public function testHidesRdfLinkForATitleWithoutAPage(): void {
		// A red link has no page id, and the export of page 0 is a 404.
		$this->assertSame(
			[],
			$this->build(
				pageId: 0,
				canCreateMainSubject: false,
				devUiEnabled: false
			)
		);
	}

	public function testShowsRdfLinkOnAPageWithoutSubjects(): void {
		// Every page has an RDF export, holding its page metadata and any Subjects, so every page links to
		// one — as the head autodiscovery tags and the endpoint itself do.
		$this->assertEquals(
			[ $this->rdfItem() ],
			$this->build(
				canCreateMainSubject: false,
				devUiEnabled: false
			)
		);
	}

	public function testHidesEditJsonWhenDevUiDisabled(): void {
		$this->assertEquals(
			[ $this->createSubjectItem(), $this->rdfItem() ],
			$this->build( devUiEnabled: false )
		);
	}

	public function testHidesCreateSubjectOnOldRevision(): void {
		$this->assertEquals(
			[ $this->rdfItem(), $this->editJsonItem() ],
			$this->build( isLatestRevision: false )
		);
	}

	public function testHidesCreateSubjectWhenUserCannotCreateSubjects(): void {
		$this->assertEquals(
			[ $this->rdfItem(), $this->editJsonItem() ],
			$this->build( canCreateMainSubject: false )
		);
	}

	public function testUsesViewJsonLabelWhenUserCannotEditSubjects(): void {
		$this->assertEquals(
			[
				$this->rdfItem(),
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
		int $pageId = self::PAGE_ID,
		bool $canCreateMainSubject = true,
		bool $canEditSubject = true,
		bool $isLatestRevision = true,
		bool $devUiEnabled = true
	): array {
		return ( new PageToolsBuilder() )->build(
			title: Title::newFromText( self::PAGE_NAME ),
			pageId: $pageId,
			canCreateMainSubject: $canCreateMainSubject,
			canEditSubject: $canEditSubject,
			isLatestRevision: $isLatestRevision,
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
