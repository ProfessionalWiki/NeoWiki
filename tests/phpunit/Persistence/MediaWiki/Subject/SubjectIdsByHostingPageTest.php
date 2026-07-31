<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectIdsByHostingPage;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectIdsByHostingPage
 */
class SubjectIdsByHostingPageTest extends TestCase {

	private const string GUID_123 = 's1111111111a123';
	private const string GUID_456 = 's1111111111a456';

	/**
	 * The lookup is an extension point, so an implementation answering with more than it was asked
	 * about is possible. Grouping an id the caller never named would index an id list that does not
	 * hold it.
	 */
	public function testIgnoresIdsThatWereNotRequested(): void {
		$requestedId = new SubjectId( self::GUID_123 );

		$pageIdentifiersLookup = $this->createStub( PageIdentifiersLookup::class );
		$pageIdentifiersLookup->method( 'getPageIdsOfSubjects' )->willReturn( [
			self::GUID_123 => new PageIdentifiers( new PageId( 7 ), 'Requested', 0 ),
			self::GUID_456 => new PageIdentifiers( new PageId( 9 ), 'Never asked about', 0 ),
		] );

		$this->assertEquals(
			[ 7 => [ $requestedId ] ],
			( new SubjectIdsByHostingPage( $pageIdentifiersLookup ) )->group(
				new SubjectIdList( [ $requestedId ] )
			)
		);
	}

}
