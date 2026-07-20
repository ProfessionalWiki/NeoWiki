<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Content;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContentHandler;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContentHandler
 * @group Database
 */
class SubjectContentHandlerCanBeUsedOnTest extends MediaWikiIntegrationTestCase {

	private function canBeUsedOn( int $namespace ): bool {
		return ( new SubjectContentHandler( SubjectContent::CONTENT_MODEL_ID ) )
			->canBeUsedOn( Title::makeTitle( $namespace, 'Subject page' ) );
	}

	public function testCannotBeUsedAsMainContentModelOfContentPages(): void {
		$this->assertFalse( $this->canBeUsedOn( NS_MAIN ) );
		$this->assertFalse( $this->canBeUsedOn( NS_HELP ) );
	}

	public function testMainSlotRejectsTheSubjectContentModel(): void {
		$handler = $this->getServiceContainer()->getSlotRoleRegistry()->getRoleHandler( SlotRecord::MAIN );

		$this->assertFalse( $handler->isAllowedModel( SubjectContent::CONTENT_MODEL_ID, $this->page() ) );
	}

	public function testSubjectSlotStillAcceptsTheSubjectContentModel(): void {
		$handler = $this->getServiceContainer()->getSlotRoleRegistry()->getRoleHandler( MediaWikiSubjectRepository::SLOT_NAME );

		$this->assertTrue( $handler->isAllowedModel( SubjectContent::CONTENT_MODEL_ID, $this->page() ) );
	}

	private function page(): PageIdentity {
		return Title::makeTitle( NS_MAIN, 'Subject page' )->toPageIdentity();
	}

}
