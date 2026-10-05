import { RestSchemaRepository } from '@/persistence/RestSchemaRepository';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { PropertyName } from '@/domain/PropertyDefinition';
import { Schema } from '@/domain/Schema';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';
import { TextType } from '@/domain/propertyTypes/Text';
import { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import { FailingPageSaver, PageSaver, SucceedingPageSaver } from '@/persistence/PageSaver.ts';

describe( 'RestSchemaRepository', () => {

	describe( 'getSchema', () => {

		function newSchemaRepository( httpClient: HttpClient ): RestSchemaRepository {
			return new RestSchemaRepository( 'https://example.com/rest.php', httpClient, new SchemaSerializer(), new SchemaDeserializer(), new SucceedingPageSaver() );
		}

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/v1/page/Schema:Employee':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } ),
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
						type: TextType.typeName,
						required: true,
					},
				},
			};

			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/v1/page/Schema:Employee':
					new Response( JSON.stringify( { source: JSON.stringify( mockSchemaContent ) } ), { status: 200 } ),
			} );

			const schemaRepository = newSchemaRepository( inMemoryHttpClient );
			const schema = await schemaRepository.getSchema( 'Employee' );

			expect( schema.getName() ).toEqual( 'Employee' );
			expect( schema.getDescription() ).toEqual( 'Employee foo bar baz' );
			expect( schema.getPropertyDefinitions().asRecord() ).toEqual( {
				LegalName: {
					name: new PropertyName( 'LegalName' ),
					type: TextType.typeName,
					description: '',
					required: true,
					multiple: false,
					uniqueItems: false,
				},
			} );
		} );

	} );

	describe( 'getSchemaSummaries', () => {

		const PAGE = { schemas: [ { name: 'Artist', description: '', propertyCount: 2 } ], nextCursor: 'next' };

		function repositoryAnswering( url: string ): RestSchemaRepository {
			return new RestSchemaRepository(
				'https://example.com/rest.php',
				new InMemoryHttpClient( { [ url ]: new Response( JSON.stringify( PAGE ), { status: 200 } ) } ),
				new SchemaSerializer(),
				new SchemaDeserializer(),
				new SucceedingPageSaver(),
			);
		}

		it( 'asks for the first page of the Schemas whose name contains the search', async () => {
			const repository = repositoryAnswering( 'https://example.com/rest.php/neowiki/v0/schemas?limit=12&search=art+gal' );

			expect( await repository.getSchemaSummaries( 'art gal', null, 12 ) ).toEqual( PAGE );
		} );

		it( 'asks for the page after the cursor', async () => {
			const repository = repositoryAnswering( 'https://example.com/rest.php/neowiki/v0/schemas?limit=12&cursor=abc%2B%3D' );

			expect( await repository.getSchemaSummaries( '', 'abc+=', 12 ) ).toEqual( PAGE );
		} );

	} );

	describe( 'saveSchema', () => {
		let repository: RestSchemaRepository;
		let mockHttpClient: HttpClient;
		let mockSerializer: SchemaSerializer;
		let pageSaver: PageSaver;
		const apiUrl = 'https://test.api.url';

		beforeEach( () => {
			mockHttpClient = {
				get: vi.fn(),
				post: vi.fn(),
				patch: vi.fn(),
				put: vi.fn(),
				delete: vi.fn(),
			};

			mockSerializer = {
				serializeSchema: vi.fn().mockImplementation(
					( schema: Schema ) => '{"serialized":"' + schema.getName() + '"}',
				),
			} as unknown as SchemaSerializer;

			pageSaver = new SucceedingPageSaver();

			repository = new RestSchemaRepository( apiUrl, mockHttpClient, mockSerializer, new SchemaDeserializer(), pageSaver );
		} );

		const testSchema = new Schema( 'TestSchema', 'Test Description', new PropertyDefinitionList( [] ) );

		it( 'should call the correct API endpoint with the right parameters', async () => {
			vi.spyOn( pageSaver, 'savePage' );

			await repository.saveSchema( testSchema, 'Comment for the edit' );

			expect( pageSaver.savePage ).toHaveBeenCalledWith(
				'Schema:TestSchema',
				'{"serialized":"TestSchema"}',
				'Comment for the edit',
				'NeoWikiSchema',
			);
		} );

		it( 'should use default summary if none provided', async () => {
			vi.spyOn( pageSaver, 'savePage' );

			await repository.saveSchema( testSchema );

			expect( pageSaver.savePage ).toHaveBeenCalledWith(
				'Schema:TestSchema',
				'{"serialized":"TestSchema"}',
				'Update schema via NeoWiki REST API',
				'NeoWikiSchema',
			);
		} );

		// The reason reaches the user as the notification's body, under a title that already
		// names the Schema, so it is passed on as the saver worded it.
		it( 'should throw the reason the save was refused for', async () => {
			repository = new RestSchemaRepository( apiUrl, mockHttpClient, mockSerializer, new SchemaDeserializer(), new FailingPageSaver() );

			await expect( repository.saveSchema( testSchema, 'Comment for the edit' ) )
				.rejects
				.toThrow( /^Some reason$/ );
		} );

		it( 'should encode the schema name', async () => {
			const schemaWithSpecialChars = new Schema(
				'Test/Schema With:Spaces',
				'Description',
				new PropertyDefinitionList( [] ),
			);

			vi.spyOn( pageSaver, 'savePage' );

			await repository.saveSchema( schemaWithSpecialChars, 'Comment for the edit' );

			expect( pageSaver.savePage ).toHaveBeenCalledWith(
				'Schema:Test%2FSchema%20With%3ASpaces',
				expect.anything(),
				'Comment for the edit',
				expect.anything(),
			);
		} );
	} );

} );
