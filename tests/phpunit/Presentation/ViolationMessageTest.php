<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Presentation\ViolationMessage;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\ViolationMessage
 */
class ViolationMessageTest extends MediaWikiIntegrationTestCase {

	public function testRendersTheFieldMessageForTheCode(): void {
		$formatter = $this->getServiceContainer()->getMessageFormatterFactory()->getTextFormatter( 'en' );
		$violation = new Violation( null, 'invalid-option', [ 'Nonexistent' ], null, Severity::Error );

		$this->assertSame(
			'"Nonexistent" is not a valid option.',
			$formatter->format( ViolationMessage::valueOf( $violation ) )
		);
	}

	public function testCallerArgIsNotExpandedAsWikitext(): void {
		$formatter = $this->getServiceContainer()->getMessageFormatterFactory()->getTextFormatter( 'en' );
		$violation = new Violation( null, 'invalid-option', [ '{{uc:hello}}' ], null, Severity::Error );

		$this->assertSame(
			'"{{uc:hello}}" is not a valid option.',
			$formatter->format( ViolationMessage::valueOf( $violation ) )
		);
	}

	public function testNamesThePropertyWhenTheViolationCarriesOne(): void {
		$formatter = $this->getServiceContainer()->getMessageFormatterFactory()->getTextFormatter( 'en' );
		$violation = new Violation( new PropertyName( 'Status' ), 'invalid-option', [ '{{uc:hello}}' ], null, Severity::Error );

		$this->assertSame(
			'Status: "{{uc:hello}}" is not a valid option.',
			$formatter->format( ViolationMessage::valueOf( $violation ) )
		);
	}

}
