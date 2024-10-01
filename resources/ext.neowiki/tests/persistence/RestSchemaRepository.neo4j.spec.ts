import { RestSchemaRepository } from '@/persistence/RestSchemaRepository';
import { describe, expect, it } from 'vitest';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';
import { TextFormat } from '@neo/domain/valueFormats/Text';

describe( 'RestSchemaRepository', () => {

	describe( 'getSchema', () => {

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/v1/page/Schema:Employee':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } )
			} );

			const schemaRepository = new RestSchemaRepository( 'https://example.com/rest.php', inMemoryHttpClient );

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
						type: 'string',
						format: TextFormat.formatName,
						required: true
					}
				}
			};

			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/v1/page/Schema:Employee':
					new Response( JSON.stringify( { source: JSON.stringify( mockSchemaContent ) } ), { status: 200 } )
			} );

			const schemaRepository = new RestSchemaRepository( 'https://example.com/rest.php', inMemoryHttpClient );
			const schema = await schemaRepository.getSchema( 'Employee' );

			expect( schema.getName() ).toEqual( 'Employee' );
			expect( schema.getDescription() ).toEqual( 'Employee foo bar baz' );
			expect( schema.getPropertyDefinitions().asRecord() ).toEqual( {
				LegalName: {
					name: new PropertyName( 'LegalName' ),
					type: 'string',
					format: TextFormat.formatName,
					description: '',
					required: true,
					multiple: false, // TODO: use dedicated format for multi-valued properties?
					uniqueItems: true
				}
			} );
		} );

	} );

} );
