import { beforeEach, describe, expect, it } from 'vitest';
import { readOwnedItems } from '@/application/modelBundle/bundleStatus';
import { CurrentStateReader } from '@/application/modelBundle/CurrentStateReader';
import type { InstallRecord } from '@/application/modelBundle/InstallRecord';
import type { JsonObject } from '@/application/modelBundle/ModelBundle';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { InMemoryDataModel } from './InMemoryDataModel';
import { installed, schemaJson, subjectEntry } from './builders';

const ID_1 = 's1demo1aaaaaaa1';
const ID_2 = 's1demo1aaaaaaa2';
const ID_3 = 's1demo1aaaaaaa3';
const ID_4 = 's1demo1aaaaaaa4';
const FEMALE = subjectEntry( { label: 'female' } );

function record( ...ids: string[] ): InstallRecord {
	return installed( ids.map( ( id ) => [ id, FEMALE ] ) );
}

describe( 'readOwnedItems', () => {

	let wiki: InMemoryDataModel;

	beforeEach( () => {
		wiki = new InMemoryDataModel();
	} );

	it( 'compares each item with the version the bundle installed', async () => {
		wiki.withSubject( ID_1, FEMALE ).withSubject( ID_2, subjectEntry( { label: 'female (edited)' } ) );
		wiki.withSubject( ID_4, FEMALE );
		wiki.unreadable.add( `subject:${ ID_4 }` );

		const items = await readOwnedItems( record( ID_1, ID_2, ID_3, ID_4 ), new CurrentStateReader( wiki ) );

		expect( items.map( ( item ) => [ item.key, item.status ] ) ).toEqual( [
			[ ID_1, 'unchanged' ],
			[ ID_2, 'changed-here' ],
			[ ID_3, 'deleted-here' ],
			[ ID_4, 'unreadable' ],
		] );
	} );

	it( 'names a Subject by its label under its Schema, as recorded once the wiki deleted it', async () => {
		wiki.withSubject( ID_1, subjectEntry( { label: 'female (edited)', schema: 'Gender' } ) );

		const items = await readOwnedItems( record( ID_1, ID_3 ), new CurrentStateReader( wiki ) );

		expect( items.map( ( item ) => [ item.name, item.schema ] ) ).toEqual( [
			[ 'female (edited)', 'Gender' ],
			[ 'female', 'Gender Types' ],
		] );
	} );

	it( 'counts a recorded Schema stored with its keys in another order as unchanged', async () => {
		const person = new SchemaDeserializer().deserialize( 'Person', { description: 'A human being', propertyDefinitions: { Name: { type: 'text' } } } );
		wiki.withSchemas( person );
		const stored = schemaJson( person );
		const olderOrder = Object.fromEntries( Object.entries( stored ).reverse() ) as JsonObject;

		const items = await readOwnedItems( installed( [], [], { items: [ { type: 'schema', key: 'Person', item: olderOrder } ] } ), new CurrentStateReader( wiki ) );

		expect( items.map( ( item ) => item.status ) ).toEqual( [ 'unchanged' ] );
	} );

} );
