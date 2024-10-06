import { RestSchemaRepository } from '@/persistence/RestSchemaRepository';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { describe, expect, it, vi, beforeEach, Mock } from 'vitest';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { Schema } from '@neo/domain/Schema';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';
import { TextFormat } from '@neo/domain/valueFormats/Text';
import { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

describe( 'RestSchemaRepository', () => {

	describe( 'getSchema', () => {

		function newSchemaRepository( httpClient: HttpClient ): RestSchemaRepository {
			return new RestSchemaRepository( 'https://example.com/rest.php', httpClient, new SchemaSerializer() );
		}

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/v1/page/Schema:Employee':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } )
			} );

			const schemaRepository = newSchemaRepository( inMemoryHttpClient );

			try {
				await schemaRepository.getSchema( 'Employee' );
			} catch ( error ) {
				expect( error ).toEqual( new Error( 'Error fetching schema' ) );
			}
		} );

		it( 'returns existing schema', async () => {
			const mockSchemaContent = {
				title: 'Employee',
				description: 'Employee foo bar baz',
				propertyDefinitions: {
					LegalName: {
						format: TextFormat.formatName,
						required: true
					}
				}
			};

			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/v1/page/Schema:Employee':
					new Response( JSON.stringify( { source: JSON.stringify( mockSchemaContent ) } ), { status: 200 } )
			} );

			const schemaRepository = newSchemaRepository( inMemoryHttpClient );
			const schema = await schemaRepository.getSchema( 'Employee' );

			expect( schema.getName() ).toEqual( 'Employee' );
			expect( schema.getDescription() ).toEqual( 'Employee foo bar baz' );
			expect( schema.getPropertyDefinitions().asRecord() ).toEqual( {
				LegalName: {
					name: new PropertyName( 'LegalName' ),
					format: TextFormat.formatName,
					description: '',
					required: true,
					multiple: false,
					uniqueItems: true
				}
			} );
		} );

	} );

	describe( 'saveSchema', () => {
		let repository: RestSchemaRepository;
		let mockHttpClient: HttpClient;
		let mockSerializer: SchemaSerializer;
		const apiUrl = 'https://test.api.url';

		beforeEach( () => {
			mockHttpClient = {
				get: vi.fn(),
				post: vi.fn(),
				patch: vi.fn(),
				delete: vi.fn()
			};

			mockSerializer = {
				serializeSchema: vi.fn().mockImplementation(
					( schema: Schema ) => '{"serialized":"' + schema.getName() + '"}'
				)
			} as unknown as SchemaSerializer;

			repository = new RestSchemaRepository( apiUrl, mockHttpClient, mockSerializer );
		} );

		const testSchema = new Schema( 'TestSchema', 'Test Description', new PropertyDefinitionList( [] ) );

		it( 'should call the correct API endpoint with the right parameters', async () => {
			( mockHttpClient.post as Mock ).mockResolvedValue( { ok: true } );

			await repository.saveSchema( testSchema );

			expect( mockHttpClient.post ).toHaveBeenCalledWith(
				`${ apiUrl }/v1/page/Schema:TestSchema`,
				{
					source: '{"serialized":"TestSchema"}',
					comment: 'Update schema via NeoWiki UI',
					content_model: 'json'
				}
			);
		} );

		it( 'should throw an error if the API response is not ok', async () => {
			( mockHttpClient.post as Mock ).mockResolvedValue( { ok: false, statusText: 'Not Found' } );

			await expect( repository.saveSchema( testSchema ) )
				.rejects
				.toThrow( 'Error saving schema: Not Found' );
		} );

		it( 'should encode the schema name in the URL', async () => {
			const schemaWithSpecialChars = new Schema(
				'Test/Schema With:Spaces',
				'Description',
				new PropertyDefinitionList( [] )
			);

			( mockHttpClient.post as Mock ).mockResolvedValue( { ok: true } );
			await repository.saveSchema( schemaWithSpecialChars );

			expect( mockHttpClient.post ).toHaveBeenCalledWith(
				`${ apiUrl }/v1/page/Schema:Test%2FSchema%20With%3ASpaces`,
				expect.anything()
			);
		} );
	} );

} );
