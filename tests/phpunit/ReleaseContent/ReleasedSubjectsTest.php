<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\ReleaseContent;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataSerializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\StatementDeserializer
 */
class ReleasedSubjectsTest extends TestCase {

	use ReadsReleaseContent;

	/**
	 * @dataProvider pageProvider
	 */
	public function testReadsEverySubjectAsStored( string $release, string $page ): void {
		$subjects = $this->readPage( $release, $page );

		$this->assertEquals(
			self::decodedReleaseContent( $release, 'Subject', $page ),
			json_decode( ( new SubjectContentDataSerializer() )->serialize( $subjects ), true )
		);
		$this->assertNotContains( ValueType::UnregisteredType, $this->valueTypesOf( $subjects ) );
	}

	public static function pageProvider(): iterable {
		return self::releaseContentPages( 'Subject' );
	}

	/**
	 * @dataProvider pageProvider
	 */
	public function testListsEverySubjectIdForThePageIndex( string $release, string $page ): void {
		$this->assertSame(
			array_keys( self::decodedReleaseContent( $release, 'Subject', $page )['subjects'] ),
			SubjectContentDataDeserializer::deserializeSubjectIds( self::releaseContent( $release, 'Subject', $page ) )
		);
	}

	private function readPage( string $release, string $page ): PageSubjects {
		return NeoWikiExtension::getInstance()->newSubjectContentDataDeserializer()->deserialize(
			self::releaseContent( $release, 'Subject', $page )
		);
	}

	/**
	 * @return ValueType[]
	 */
	private function valueTypesOf( PageSubjects $subjects ): array {
		$valueTypes = [];

		foreach ( $subjects->getAllSubjects()->asArray() as $subject ) {
			foreach ( $subject->getStatements()->asArray() as $statement ) {
				$valueTypes[] = $statement->getValue()->getType();
			}
		}

		return $valueTypes;
	}

}
