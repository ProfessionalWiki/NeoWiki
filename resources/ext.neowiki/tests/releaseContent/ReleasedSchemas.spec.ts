import { describe, expect, it } from 'vitest';
import { Neo } from '@/Neo';
import type { Schema } from '@/domain/Schema';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { readReleaseContent, releaseContentPages } from './releaseContent';

function readSchema( release: string, schemaName: string ): Schema {
	return new SchemaDeserializer().deserialize( schemaName, readReleaseContent( release, 'Schema', schemaName ) );
}

/**
 * A stored property definition as the frontend writes it back: a Constraint with the default severity as its bare
 * value, and a bare text default as a list.
 */
function asTheFrontendWritesIt( property: Record<string, unknown> ): Record<string, unknown> {
	return Object.fromEntries( Object.entries( property ).map( ( [ key, value ]: [ string, any ] ) => {
		if ( key === 'default' && typeof value === 'string' ) {
			return [ key, [ value ] ];
		}

		return [ key, value?.severity === 'warning' ? value.value ?? true : value ];
	} ) );
}

const properties = releaseContentPages( 'Schema' ).flatMap( ( { release, page } ) =>
	Object.keys( readReleaseContent( release, 'Schema', page ).propertyDefinitions ).map(
		( property ) => ( { release, page, property } ),
	),
);

describe( 'Schemas as releases stored them', () => {

	it.each( releaseContentPages( 'Schema' ) )( 'reads $page ($release) as stored', ( { release, page } ) => {
		const stored = readReleaseContent( release, 'Schema', page );
		const schema = readSchema( release, page );

		expect( {
			description: schema.getDescription(),
			subjectsAreDependent: schema.subjectsAreDependent(),
			propertyNames: [ ...schema.getPropertyDefinitions() ].map( ( property ) => property.name.toString() ),
		} ).toEqual( {
			description: stored.description ?? '',
			subjectsAreDependent: stored.subjectsAreDependent ?? false,
			propertyNames: Object.keys( stored.propertyDefinitions ),
		} );
	} );

	it.each( properties )( 'reads $page ($release): $property as stored', ( { release, page, property } ) => {
		const stored = readReleaseContent( release, 'Schema', page ).propertyDefinitions[ property ];
		const written = new SchemaSerializer().schemaToJson( readSchema( release, page ) ).propertyDefinitions as Record<string, unknown>;

		expect( Neo.getInstance().getPropertyTypeRegistry().hasType( stored.type ) ).toBe( true );
		expect( written[ property ] ).toMatchObject( asTheFrontendWritesIt( stored ) );
	} );

} );
