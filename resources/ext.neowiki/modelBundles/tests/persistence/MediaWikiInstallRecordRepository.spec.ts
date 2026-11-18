import { describe, expect, it } from 'vitest';
import { MediaWikiInstallRecordRepository } from '../../src/persistence/MediaWikiInstallRecordRepository';
import type { ActionApi } from '../../src/persistence/ActionApi';
import type { InstallRecord } from '../../src/application/InstallRecord';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { stubActionApi } from './stubActionApi';

/**
 * As NeoWiki saves a Schema now.
 */
function savedSchema( name: string, json: Record<string, unknown> ): Record<string, unknown> {
	return new SchemaSerializer().schemaToJson( new SchemaDeserializer().deserialize( name, json ) );
}

const RECORD: InstallRecord = {
	name: 'Vocabularies',
	version: '1',
	description: '',
	source: 'file',
	items: [
		{ type: 'schema', key: 'Gender Types', item: savedSchema( 'Gender Types', { propertyDefinitions: {} } ) },
		{ type: 'subject', key: 's1demo1aaaaaaa1', hash: 'abc', label: 'female', schema: 'Gender Types' },
	],
	declined: [ { type: 'subject', key: 's1demo1aaaaaaa2' } ],
	revisionId: 42,
};

const RECORD_PAGE_JSON = JSON.stringify( { version: '1', description: '', source: 'file', items: RECORD.items, declined: RECORD.declined } );

function page( title: string, content?: string, revid = 42 ): Record<string, unknown> {
	return content === undefined ? { title } : { title, revisions: [ { revid, slots: { main: { content } } } ] };
}

function savingApi( saved: Record<string, unknown>[] ): ActionApi {
	return stubActionApi( {
		postWithEditToken: async ( parameters ) => {
			saved.push( parameters );
			return { edit: { result: 'Success' } };
		},
	} );
}

describe( 'MediaWikiInstallRecordRepository', () => {

	it( 'reads every record page, following the continuation, past a page that is not a record', async () => {
		const responses = [
			{ continue: { rvcontinue: '1', continue: 'gapcontinue||' }, query: { pages: [
				page( 'MediaWiki:NeoWiki/Model bundles/Vocabularies.json', RECORD_PAGE_JSON ),
				page( 'MediaWiki:NeoWiki/Model bundles/Broken.json', '{ not json' ),
				page( 'MediaWiki:NeoWiki/Model bundles/Agents.json' ),
			] } },
			{ query: { pages: [
				page( 'MediaWiki:NeoWiki/Model bundles/Agents.json', RECORD_PAGE_JSON ),
			] } },
		];
		const asked: Record<string, unknown>[] = [];
		const api = stubActionApi( {
			get: async ( parameters ) => {
				asked.push( parameters );
				return responses[ asked.length - 1 ];
			},
		} );

		const records = await new MediaWikiInstallRecordRepository( api ).getInstallRecords();

		expect( records.map( ( record ) => record.name ) ).toEqual( [ 'Agents', 'Vocabularies' ] );
		expect( asked[ 1 ] ).toMatchObject( { rvcontinue: '1' } );
	} );

	it( 'names a record after its page, with the revision it was read from', async () => {
		const api = stubActionApi( {
			get: async () => ( { query: { pages: [ page( 'MediaWiki:NeoWiki/Model bundles/Vocabularies.json', RECORD_PAGE_JSON ) ] } } ),
		} );

		expect( await new MediaWikiInstallRecordRepository( api ).getInstallRecords() ).toEqual( [ RECORD ] );
	} );

	it( 'skips a record that holds hashes alone, from before records held the items', async () => {
		const oldRecord = JSON.stringify( { version: '1', items: [ { type: 'schema', key: 'Gender Types', hash: '1c2ba782c97901' } ] } );
		const api = stubActionApi( {
			get: async () => ( { query: { pages: [ page( 'MediaWiki:NeoWiki/Model bundles/Foo.json', oldRecord ) ] } } ),
		} );

		expect( await new MediaWikiInstallRecordRepository( api ).getInstallRecords() ).toEqual( [] );
	} );

	it( 'reads a record from before records kept declined items as declining none', async () => {
		const stored = JSON.stringify( { version: '1', items: [] } );
		const api = stubActionApi( {
			get: async () => ( { query: { pages: [ page( 'MediaWiki:NeoWiki/Model bundles/Vocabularies.json', stored ) ] } } ),
		} );

		const records = await new MediaWikiInstallRecordRepository( api ).getInstallRecords();

		expect( records[ 0 ].declined ).toEqual( [] );
	} );

	it( 'reads a recorded Schema as NeoWiki saves Schemas now', async () => {
		const stored = JSON.stringify( { items: [ { type: 'schema', key: 'Person', item: { propertyDefinitions: { Name: { type: 'text' } } } } ] } );
		const api = stubActionApi( {
			get: async () => ( { query: { pages: [ page( 'MediaWiki:NeoWiki/Model bundles/Vocabularies.json', stored ) ] } } ),
		} );

		const records = await new MediaWikiInstallRecordRepository( api ).getInstallRecords();

		expect( records[ 0 ].items ).toEqual( [
			{ type: 'schema', key: 'Person', item: savedSchema( 'Person', { propertyDefinitions: { Name: { type: 'text' } } } ) },
		] );
	} );

	it( 'saves a record as a JSON page titled by the bundle\'s name, which the page does not repeat', async () => {
		const saved: Record<string, unknown>[] = [];

		await new MediaWikiInstallRecordRepository( savingApi( saved ) ).saveInstallRecord( { ...RECORD, name: 'Vocabularies_ ' }, 'Install' );

		expect( saved[ 0 ] ).toMatchObject( {
			action: 'edit',
			title: 'MediaWiki:NeoWiki/Model bundles/Vocabularies.json',
			contentmodel: 'json',
			summary: 'Install',
		} );
		expect( JSON.parse( saved[ 0 ].text as string ) ).toEqual( JSON.parse( RECORD_PAGE_JSON ) );
	} );

	it( 'saves over the revision the record was read from', async () => {
		const saved: Record<string, unknown>[] = [];

		await new MediaWikiInstallRecordRepository( savingApi( saved ) ).saveInstallRecord( RECORD, 'Update' );

		expect( saved[ 0 ] ).toMatchObject( { baserevid: 42 } );
		expect( saved[ 0 ] ).not.toHaveProperty( 'createonly' );
	} );

	it( 'creates the page of a record read from none, so that one saved meanwhile is not overwritten', async () => {
		const saved: Record<string, unknown>[] = [];

		await new MediaWikiInstallRecordRepository( savingApi( saved ) ).saveInstallRecord( { ...RECORD, revisionId: null }, 'Install' );

		expect( saved[ 0 ] ).toMatchObject( { createonly: true } );
		expect( saved[ 0 ] ).not.toHaveProperty( 'baserevid' );
	} );

	it( 'fails when the wiki stops the save', async () => {
		const api = stubActionApi( { postWithEditToken: async () => ( { edit: { result: 'Failure' } } ) } );

		await expect( new MediaWikiInstallRecordRepository( api ).saveInstallRecord( RECORD, '' ) ).rejects.toThrow( 'Failure' );
	} );

} );
