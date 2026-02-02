import { RestSchemaRepository, schemaFromJson, propertyDefinitionsFromJson } from '@/persistence/RestSchemaRepository';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { PropertyName } from '@/domain/PropertyDefinition';
import { Schema } from '@/domain/Schema';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';
import { TextType } from '@/domain/propertyTypes/Text';
import { NumberType } from '@/domain/propertyTypes/Number';
import { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import { FailingPageSaver, PageSaver, SucceedingPageSaver } from '@/persistence/PageSaver.ts';

describe( 'RestSchemaRepository', () => {

	describe( 'getSchema', () => {

		function newSchemaRepository( httpClient: HttpClient ): RestSchemaRepository {
			return new RestSchemaRepository( 'https://example.com/rest.php', httpClient, new SchemaSerializer(), new SucceedingPageSaver() );
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
					uniqueItems: true,
				},
			} );
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
				delete: vi.fn(),
			};

			mockSerializer = {
				serializeSchema: vi.fn().mockImplementation(
					( schema: Schema ) => '{"serialized":"' + schema.getName() + '"}',
				),
			} as unknown as SchemaSerializer;

			pageSaver = new SucceedingPageSaver();

			repository = new RestSchemaRepository( apiUrl, mockHttpClient, mockSerializer, pageSaver );
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

		it( 'should throw an error if the API response failed', async () => {
			repository = new RestSchemaRepository( apiUrl, mockHttpClient, mockSerializer, new FailingPageSaver() );

			await expect( repository.saveSchema( testSchema, 'Comment for the edit' ) )
				.rejects
				.toThrow( 'Error saving schema: Some reason' );
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

describe( 'schemaFromJson', () => {

	it( 'deserializes a schema with property definitions', () => {
		const schema = schemaFromJson( 'Employee', {
			description: 'An employee',
			propertyDefinitions: {
				Name: { type: TextType.typeName, required: true },
				Age: { type: NumberType.typeName, required: false },
			},
		} );

		expect( schema.getName() ).toEqual( 'Employee' );
		expect( schema.getDescription() ).toEqual( 'An employee' );
		expect( schema.getPropertyDefinitions().asRecord() ).toEqual( {
			Name: {
				name: new PropertyName( 'Name' ),
				type: TextType.typeName,
				description: '',
				required: true,
				multiple: false,
				uniqueItems: true,
			},
			Age: {
				name: new PropertyName( 'Age' ),
				type: NumberType.typeName,
				description: '',
				required: false,
				minimum: undefined,
				maximum: undefined,
			},
		} );
	} );

	it( 'deserializes a schema with no property definitions', () => {
		const schema = schemaFromJson( 'Empty', {
			description: 'Empty schema',
			propertyDefinitions: {},
		} );

		expect( schema.getName() ).toEqual( 'Empty' );
		expect( schema.getDescription() ).toEqual( 'Empty schema' );
		expect( schema.getPropertyDefinitions().asRecord() ).toEqual( {} );
	} );

} );

describe( 'propertyDefinitionsFromJson', () => {

	it( 'deserializes multiple property definitions', () => {
		const definitions = propertyDefinitionsFromJson( {
			Website: { type: TextType.typeName, required: false },
			Count: { type: NumberType.typeName, required: true },
		} );

		expect( definitions.asRecord() ).toEqual( {
			Website: {
				name: new PropertyName( 'Website' ),
				type: TextType.typeName,
				description: '',
				required: false,
				multiple: false,
				uniqueItems: true,
			},
			Count: {
				name: new PropertyName( 'Count' ),
				type: NumberType.typeName,
				description: '',
				required: true,
				minimum: undefined,
				maximum: undefined,
			},
		} );
	} );

	it( 'returns empty list for empty input', () => {
		const definitions = propertyDefinitionsFromJson( {} );

		expect( definitions.asRecord() ).toEqual( {} );
	} );

} );
