<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\GraphDatabase;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage
 */
class BackendFailureMessageTest extends TestCase {

	private const SECRET = 'sekrit';

	public function testAMessageWithNoCredentialsInItIsLeftAlone(): void {
		$message = 'Could not reach http://qlever.example/api/neowiki: connection refused';

		$this->assertSame( $message, BackendFailureMessage::withoutCredentials( $message ) );
	}

	public function testTheUserinfoOfAConnectionUriIsRemoved(): void {
		$this->assertSame(
			"Cannot connect to any server on alias: bolt with Uris: ('bolt://neo:7687')",
			BackendFailureMessage::withoutCredentials(
				"Cannot connect to any server on alias: bolt with Uris: ('bolt://neo4j:sekrit@neo:7687')"
			)
		);
	}

	/**
	 * @dataProvider queryStringSecretProvider
	 */
	public function testAnAccessTokenInAQueryStringIsRemoved( string $parameter ): void {
		$this->assertSame(
			'POST http://qlever.example/api/neowiki?' . $parameter . '= failed with 401',
			BackendFailureMessage::withoutCredentials(
				'POST http://qlever.example/api/neowiki?' . $parameter . '=' . self::SECRET . ' failed with 401'
			)
		);
	}

	public function queryStringSecretProvider(): iterable {
		yield 'a QLever access token' => [ 'access-token' ];
		yield 'spelled with an underscore' => [ 'access_token' ];
		yield 'spelled without a separator' => [ 'accesstoken' ];
		yield 'spelled in upper case' => [ 'ACCESS-TOKEN' ];
		yield 'a bare token' => [ 'token' ];
		yield 'an API key' => [ 'api_key' ];
		yield 'a password' => [ 'password' ];
	}

	public function testAQueryStringParameterThatIsNotACredentialIsKept(): void {
		$message = 'POST http://qlever.example/api/neowiki?format=turtle failed with 400';

		$this->assertSame( $message, BackendFailureMessage::withoutCredentials( $message ) );
	}

	/**
	 * The status code after the endpoint is often the only part of the message worth reading, so a
	 * redaction must stop at the credential rather than run to the end of the line.
	 */
	public function testWhatFollowsARedactedQueryStringIsKept(): void {
		$this->assertSame(
			'POST http://qlever.example/api/neowiki?access-token=&format=turtle: 401 Unauthorized',
			BackendFailureMessage::withoutCredentials(
				'POST http://qlever.example/api/neowiki?access-token=' . self::SECRET
				. '&format=turtle: 401 Unauthorized'
			)
		);
	}

	public function testABearerTokenIsRemoved(): void {
		$this->assertSame(
			'Rejected Authorization: Bearer (401)',
			BackendFailureMessage::withoutCredentials(
				'Rejected Authorization: Bearer eyJhbGci.eyJzdWIi-0K/9w== (401)'
			)
		);
	}

	/**
	 * Nothing distinguishes a token from a word, so whatever follows "Bearer" goes. Losing a word of
	 * prose is the right way round for a redaction.
	 */
	public function testWhateverFollowsTheWordBearerGoesWithIt(): void {
		$this->assertSame(
			'The store expects a Bearer and none was configured',
			BackendFailureMessage::withoutCredentials( 'The store expects a Bearer token and none was configured' )
		);
	}

}
