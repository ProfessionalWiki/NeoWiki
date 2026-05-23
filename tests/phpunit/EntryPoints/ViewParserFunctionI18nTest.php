<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use PHPUnit\Framework\TestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\ViewParserFunction
 */
class ViewParserFunctionI18nTest extends TestCase {

	/**
	 * Error messages are rendered via Message::escaped(), which HTML-escapes
	 * the full message text. HTML markup inside the message would surface in
	 * the page as literal &lt;tag&gt; entities. ViewParserFunctionTest cannot
	 * catch this because it mocks Parser::msg() with a marker-free RawMessage.
	 */
	public function testViewErrorMessagesContainNoHtmlMarkup(): void {
		$messages = $this->loadEnMessages();

		foreach ( $messages as $key => $value ) {
			if ( !str_starts_with( $key, 'neowiki-view-error-' ) ) {
				continue;
			}

			$this->assertStringNotContainsString(
				'<',
				$value,
				"Message $key contains HTML markup; Message::escaped() will leak entity references into rendered HTML."
			);
		}
	}

	/**
	 * @return array<string, string>
	 */
	private function loadEnMessages(): array {
		$path = __DIR__ . '/../../../i18n/en.json';
		$contents = file_get_contents( $path );

		$this->assertNotFalse( $contents, "Failed to read $path" );

		$messages = json_decode( $contents, associative: true, flags: JSON_THROW_ON_ERROR );

		$this->assertIsArray( $messages );

		return $messages;
	}

}
