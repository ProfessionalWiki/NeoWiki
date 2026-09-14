<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use PHPUnit\Framework\TestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\ViewParserFunction
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\CreateSubjectParserFunction
 */
class ParserFunctionErrorI18nTest extends TestCase {

	private const array ERROR_PREFIXES = [ 'neowiki-view-error-', 'neowiki-create-subject-error-' ];

	/**
	 * Error messages are rendered via Message::escaped(), which HTML-escapes
	 * the full message text. HTML markup inside the message would surface in
	 * the page as literal &lt;tag&gt; entities. The parser-function tests cannot
	 * catch this because they mock Parser::msg() with a marker-free RawMessage.
	 */
	public function testParserFunctionErrorMessagesContainNoHtmlMarkup(): void {
		$messages = $this->loadEnMessages();

		foreach ( self::ERROR_PREFIXES as $prefix ) {
			$errorMessages = self::messagesWithKeyPrefix( $messages, $prefix );

			$this->assertNotEmpty( $errorMessages, "No message key starts with $prefix" );

			foreach ( $errorMessages as $key => $value ) {
				$this->assertStringNotContainsString(
					'<',
					$value,
					"Message $key contains HTML markup; Message::escaped() will leak entity references into rendered HTML."
				);
			}
		}
	}

	/**
	 * @param array<string, string> $messages
	 * @return array<string, string>
	 */
	private static function messagesWithKeyPrefix( array $messages, string $prefix ): array {
		return array_filter(
			$messages,
			static fn ( string $key ): bool => str_starts_with( $key, $prefix ),
			ARRAY_FILTER_USE_KEY
		);
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
