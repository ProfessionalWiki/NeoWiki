<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummary;
use ProfessionalWiki\NeoWiki\Presentation\SubjectSummarySerializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\SubjectSummarySerializer
 */
class SubjectSummarySerializerTest extends TestCase {

	public function testSerializesTheLabelsOfTheSchemaBesideIt(): void {
		$this->assertSame(
			[ 'de' => 'Unternehmen' ],
			SubjectSummarySerializer::serialize( $this->summary( [ 'de' => 'Unternehmen' ] ) )['schemaLabels']
		);
	}

	public function testLeavesOutTheLabelsOfASchemaWithoutThem(): void {
		$this->assertArrayNotHasKey( 'schemaLabels', SubjectSummarySerializer::serialize( $this->summary( [] ) ) );
	}

	/**
	 * @param array<string, string> $schemaLabels
	 */
	private function summary( array $schemaLabels ): SubjectSummary {
		return new SubjectSummary(
			subjectId: 's1demo1aaaaaaa1',
			displayName: 'ACME Inc',
			displayNameIsGenerated: false,
			schemaName: 'Company',
			pageId: 42,
			pageTitle: 'ACME Inc',
			lastEdited: '2026-11-06T12:00:00Z',
			hostSubject: null,
			schemaLabels: $schemaLabels,
		);
	}

}
