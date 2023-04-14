<?php

declare( strict_types = 1 );

namespace Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentData;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentData
 */
class SubjectContentDataTest extends TestCase {

	public function testGetAllSubjectsReturnsMainSubjectFirst(): void {
		$data = new SubjectContentData(
			TestSubject::build( id: TestSubject::ZERO_GUID ),
			TestSubject::newMap()
		);

		$this->assertSame(
			TestSubject::ZERO_GUID,
			$data->getAllSubjects()->asArray()[0]->id->text
		);
	}

}
