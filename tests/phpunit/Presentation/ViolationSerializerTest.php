<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Presentation\ViolationSerializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\ViolationSerializer
 */
class ViolationSerializerTest extends TestCase {

	public function testSerializesViolationWithAllFields(): void {
		$serialized = ViolationSerializer::serialize(
			new Violation(
				propertyName: new PropertyName( 'Website' ),
				code: 'invalid-url',
				args: [ 'bad' ],
				valuePartIndex: 2,
			)
		);

		$this->assertSame(
			[
				'propertyName' => 'Website',
				'code' => 'invalid-url',
				'args' => [ 'bad' ],
				'severity' => 'warning',
				'valuePartIndex' => 2,
			],
			$serialized
		);
	}

	public function testSerializesSubjectLevelViolationWithNullPropertyName(): void {
		$serialized = ViolationSerializer::serialize(
			new Violation( propertyName: null, code: 'label-required' )
		);

		$this->assertNull( $serialized['propertyName'] );
		$this->assertSame( 'label-required', $serialized['code'] );
		$this->assertSame( [], $serialized['args'] );
	}

	public function testOmitsValuePartIndexWhenNull(): void {
		$serialized = ViolationSerializer::serialize(
			new Violation( propertyName: null, code: 'required' )
		);

		$this->assertArrayNotHasKey( 'valuePartIndex', $serialized );
	}

	public function testSerializeManyMapsList(): void {
		$serialized = ViolationSerializer::serializeMany( [
			new Violation( propertyName: null, code: 'label-required' ),
			new Violation( propertyName: new PropertyName( 'Age' ), code: 'max-value', args: [ 100 ] ),
		] );

		$this->assertCount( 2, $serialized );
		$this->assertSame( 'label-required', $serialized[0]['code'] );
		$this->assertSame( 'max-value', $serialized[1]['code'] );
	}

}
