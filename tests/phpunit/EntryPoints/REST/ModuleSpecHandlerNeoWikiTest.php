<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Rest\BasicAccess\StaticBasicAuthorizer;
use MediaWiki\Rest\Handler\ModuleSpecHandler;
use MediaWiki\Rest\Reporter\MWErrorReporter;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseFactory;
use MediaWiki\Rest\Router;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use Wikimedia\Message\ITextFormatter;
use Wikimedia\Message\MessageSpecifier;

/**
 * Integration test: hits ModuleSpecHandler for all NeoWiki REST routes and asserts every
 * route in extension.json appears in the emitted OpenAPI 3.0 spec with declared params
 * and, for mutating routes, a requestBody.
 *
 * @coversNothing
 * @group Database
 */
class ModuleSpecHandlerNeoWikiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	private function buildRouter(): Router {
		$services = $this->getServiceContainer();
		$authority = $this->mockRegisteredUltimateAuthority();
		$objectFactory = $services->getObjectFactory();
		$restValidator = new Validator( $objectFactory, new RequestData(), $authority );

		$formatter = new class implements ITextFormatter {
			public function getLangCode(): string {
				return 'qqx';
			}

			public function format( MessageSpecifier $message ): string {
				return $message->getKey();
			}
		};

		return new Router(
			[],
			ExtensionRegistry::getInstance()->getAttribute( 'RestRoutes' ),
			new ServiceOptions( Router::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getLocalServerObjectCache(),
			new ResponseFactory( [ $formatter ] ),
			new StaticBasicAuthorizer(),
			$authority,
			$objectFactory,
			$restValidator,
			new MWErrorReporter(),
			$services->getHookContainer(),
			$this->getSession( true )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fetchSpec(): array {
		$response = $this->executeHandler(
			new ModuleSpecHandler( $this->getServiceContainer()->getMainConfig() ),
			new RequestData( [
				'method' => 'GET',
				// '-' is the ExtraRoutesModule hack: ModuleSpecHandler maps '-' to the empty module prefix,
				// which is the catch-all module that holds all extension.json RestRoutes.
				'pathParams' => [ 'module' => '-' ],
			] ),
			[],
			[],
			[],
			[],
			null,
			null,
			$this->buildRouter()
		);

		$body = $response->getBody();
		$body->rewind();
		$decoded = json_decode( $body->getContents(), true );

		self::assertIsArray( $decoded, 'ModuleSpecHandler response must be a JSON object' );
		self::assertNotEmpty( $decoded['paths'] ?? [], 'Emitted OpenAPI spec must contain paths' );
		return $decoded;
	}

	/**
	 * @return list<array{path: string, method: string[], factory: string}>
	 */
	private function readRestRoutesFromExtensionJson(): array {
		$path = __DIR__ . '/../../../../extension.json';
		$contents = file_get_contents( $path );
		self::assertNotFalse( $contents, "Could not read extension.json at $path" );

		$extensionJson = json_decode( $contents, true );
		self::assertIsArray( $extensionJson );
		self::assertArrayHasKey( 'RestRoutes', $extensionJson );

		return array_map(
			static fn( array $r ) => [
				'path' => $r['path'],
				'method' => is_array( $r['method'] ) ? $r['method'] : [ $r['method'] ],
				'factory' => $r['factory'],
			],
			$extensionJson['RestRoutes']
		);
	}

	/**
	 * @return list<string>
	 */
	private function emittedParameterNames( array $operation ): array {
		$names = [];
		foreach ( $operation['parameters'] ?? [] as $param ) {
			self::assertIsArray( $param );
			$names[] = $param['name'];
		}
		return $names;
	}

	private function assertDeclaredBodyParamsPresent( array $route, array $spec ): void {
		$mutating = [ 'post', 'put', 'patch', 'delete' ];
		$methods = array_map( 'strtolower', $route['method'] );
		$mutatingMethods = array_intersect( $methods, $mutating );

		if ( $mutatingMethods === [] ) {
			return;
		}

		$handler = call_user_func( $route['factory'] );
		$declared = $handler->getBodyParamSettings();

		// Handlers with no declared params are not checked here; presence of the operation itself
		// is covered by testAllRegisteredRoutesArePresent.
		if ( $declared === [] ) {
			return;
		}

		$routePath = $route['path'];
		self::assertArrayHasKey( $routePath, $spec['paths'], "Route $routePath missing from spec" );
		$operations = $spec['paths'][$routePath];
		self::assertIsArray( $operations );

		foreach ( $mutatingMethods as $method ) {
			self::assertArrayHasKey( $method, $operations, "Method $method missing at $routePath" );
			$op = $operations[$method];
			self::assertIsArray( $op );

			self::assertArrayHasKey(
				'requestBody',
				$op,
				"$method $routePath has body params declared but no requestBody in spec"
			);

			$content = $op['requestBody']['content'] ?? [];
			self::assertIsArray( $content );
			self::assertArrayHasKey(
				'application/json',
				$content,
				"$method $routePath requestBody has no application/json content"
			);

			$properties = $content['application/json']['schema']['properties'] ?? [];
			self::assertIsArray( $properties );

			foreach ( array_keys( $declared ) as $paramName ) {
				$this->assertArrayHasKey(
					$paramName,
					$properties,
					"Body param '$paramName' declared in getBodyParamSettings() for $method $routePath is missing from emitted spec requestBody properties"
				);
			}
		}
	}

	public function testEmitsOpenApi3Document(): void {
		$spec = $this->fetchSpec();

		$this->assertArrayHasKey( 'openapi', $spec );
		$this->assertIsString( $spec['openapi'] );
		$this->assertStringStartsWith( '3.', $spec['openapi'] );
		$this->assertArrayHasKey( 'info', $spec );
		$this->assertArrayHasKey( 'paths', $spec );
	}

	public function testAllRegisteredRoutesArePresent(): void {
		$spec = $this->fetchSpec();
		$paths = $spec['paths'];
		self::assertIsArray( $paths );

		foreach ( $this->readRestRoutesFromExtensionJson() as $route ) {
			$routePath = $route['path'];
			$this->assertArrayHasKey( $routePath, $paths, "Route $routePath missing from OpenAPI spec" );

			$operations = $paths[$routePath];
			self::assertIsArray( $operations );

			foreach ( $route['method'] as $method ) {
				$this->assertArrayHasKey(
					strtolower( $method ),
					$operations,
					"Method $method not declared at $routePath"
				);
			}
		}
	}

	public function testAllPathAndQueryParamsHaveDescriptions(): void {
		// Body param descriptions are not checked here: Validator::getParameterSchema() (used to
		// render requestBody.content.application/json.schema.properties) does not emit the
		// PARAM_DESCRIPTION value. This is an MW framework limitation, not a gap in our handlers.
		$spec = $this->fetchSpec();
		$paths = $spec['paths'];
		self::assertIsArray( $paths );

		foreach ( $paths as $specPath => $operations ) {
			self::assertIsString( $specPath );
			self::assertIsArray( $operations );

			foreach ( $operations as $method => $op ) {
				self::assertIsString( $method );
				self::assertIsArray( $op );

				foreach ( $op['parameters'] ?? [] as $param ) {
					self::assertIsArray( $param );
					$this->assertNotEmpty(
						$param['description'] ?? '',
						"Param '{$param['name']}' at $method $specPath has empty description"
					);
				}
			}
		}
	}

	public function testMutatingRoutesHaveRequestBody(): void {
		$spec = $this->fetchSpec();
		$paths = $spec['paths'];
		self::assertIsArray( $paths );

		$mutating = [ 'post', 'put', 'patch', 'delete' ];

		foreach ( $paths as $specPath => $operations ) {
			self::assertIsString( $specPath );
			self::assertIsArray( $operations );

			foreach ( $operations as $method => $op ) {
				self::assertIsString( $method );
				self::assertIsArray( $op );

				if ( in_array( $method, $mutating, true ) ) {
					$this->assertArrayHasKey(
						'requestBody',
						$op,
						"$method $specPath is a mutating route but has no requestBody"
					);
				}
			}
		}
	}

	public function testEmittedSpecContainsAllDeclaredParamSettings(): void {
		$spec = $this->fetchSpec();
		$paths = $spec['paths'];
		self::assertIsArray( $paths );

		foreach ( $this->readRestRoutesFromExtensionJson() as $route ) {
			$handler = call_user_func( $route['factory'] );
			$declared = $handler->getParamSettings();

			// Handlers with no declared params are not checked here; presence of the operation itself
			// is covered by testAllRegisteredRoutesArePresent.
			if ( $declared === [] ) {
				continue;
			}

			$routePath = $route['path'];
			self::assertArrayHasKey( $routePath, $paths, "Route $routePath missing from spec" );
			$operations = $paths[$routePath];
			self::assertIsArray( $operations );

			foreach ( $route['method'] as $method ) {
				$methodKey = strtolower( $method );
				self::assertArrayHasKey( $methodKey, $operations, "Method $method missing at $routePath" );
				$op = $operations[$methodKey];
				self::assertIsArray( $op );

				$emittedNames = $this->emittedParameterNames( $op );

				foreach ( array_keys( $declared ) as $paramName ) {
					$this->assertContains(
						$paramName,
						$emittedNames,
						"Param '$paramName' declared in getParamSettings() for $method $routePath is missing from emitted spec parameters"
					);
				}
			}
		}
	}

	public function testEmittedSpecContainsAllDeclaredBodyParamSettings(): void {
		$spec = $this->fetchSpec();

		foreach ( $this->readRestRoutesFromExtensionJson() as $route ) {
			$this->assertDeclaredBodyParamsPresent( $route, $spec );
		}
	}

}
