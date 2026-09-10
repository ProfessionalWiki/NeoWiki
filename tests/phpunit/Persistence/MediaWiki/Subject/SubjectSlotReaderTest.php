<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use MediaWiki\Content\Content;
use MediaWiki\Content\TextContent;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectSlotReader;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectSlotReader
 */
class SubjectSlotReaderTest extends TestCase {

	public function testReturnsTheContentOfTheSubjectSlot(): void {
		$content = SubjectContent::newEmpty();

		$this->assertSame( $content, SubjectSlotReader::read( $this->newRevisionHolding( $content ) ) );
	}

	public function testReturnsNullWhenTheRevisionHasNoSubjectSlot(): void {
		$revision = $this->createStub( RevisionRecord::class );
		$revision->method( 'getContent' )->willThrowException( new RevisionAccessException( 'No such slot' ) );

		$this->assertNull( SubjectSlotReader::read( $revision ) );
	}

	public function testReturnsNullWhenTheSlotHoldsOtherContent(): void {
		$this->assertNull( SubjectSlotReader::read( $this->newRevisionHolding( new TextContent( 'not a Subject' ) ) ) );
	}

	private function newRevisionHolding( Content $content ): RevisionRecord {
		$revision = $this->createStub( RevisionRecord::class );
		$revision->method( 'getContent' )->willReturn( $content );

		return $revision;
	}

}
