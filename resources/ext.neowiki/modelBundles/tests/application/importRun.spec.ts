import { beforeEach, describe, expect, it } from 'vitest';
import type { ImportPlan } from '../../src/application/importPlan';
import { runImport, type ImportOutcome } from '../../src/application/importRun';
import { itemHash, schemaItem, subjectItem } from '../../src/application/canonical';
import { recordedHash } from '../../src/application/InstallRecord';
import { itemId, type SubjectEntry } from '../../src/application/ModelBundle';
import { newSchema } from '@/TestHelpers';
import { setupMwMock } from '../../../tests/VueTestHelpers';
import { InMemoryDataModel } from './InMemoryDataModel';
import { bundleOf, installed, planOn, schemaJson, subjectEntry } from './builders';

const ID_1 = 's1demo1aaaaaaa1';
const ID_2 = 's1demo1aaaaaaa2';
const ID_3 = 's1demo1aaaaaaa3';
const FEMALE = subjectEntry( { label: 'female' } );
const WOMAN = subjectEntry( { label: 'woman' } );

let wiki: InMemoryDataModel;

function hashOf( entry: SubjectEntry ): string {
	return itemHash( subjectItem( entry ) );
}

function run(
	importPlan: ImportPlan,
	chosen: string[] = importPlan.items.filter( ( item ) => item.selectedByDefault ).map( ( item ) => item.id ),
	subjectFirst = false,
): Promise<ImportOutcome> {
	return runImport( importPlan, new Set( chosen ), {
		repository: wiki,
		recordRepository: wiki,
		comment: 'Install',
		source: 'https://example.org/vocabularies.json',
		subjectFirst,
	} );
}

function results( outcome: ImportOutcome ): string[] {
	return outcome.items.map( ( item ) => `${ item.result } ${ item.item.key }` + ( item.reason === null ? '' : `: ${ item.reason }` ) );
}

function recordedHashes(): Record<string, string> {
	return Object.fromEntries( ( wiki.recordOf( 'Vocabularies' )?.items ?? [] ).map( ( item ) => [ item.key, recordedHash( item ) ] ) );
}

describe( 'runImport', () => {

	beforeEach( () => {
		setupMwMock();
		wiki = new InMemoryDataModel();
	} );

	it( 'writes Schemas, Layouts, Mappings and Subjects in that order, then removes Subjects before Schemas', async () => {
		const gone = newSchema( { title: 'Gone' } );
		wiki.records = [ installed( [ [ ID_3, subjectEntry( { schema: 'Gone' } ) ] ], [ gone ] ) ];
		wiki.withSchemas( gone ).withSubject( ID_3, subjectEntry( { schema: 'Gone' } ) );

		const importPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ], [ newSchema( { title: 'Gender Types' } ) ], {
			layouts: new Map( [ [ 'Card', { schema: 'Gender Types', type: 'infobox' } ] ] ),
			mappings: new Map( [ [ 'EDM', { version: 1, schemas: {} } ] ] ),
		} ) );
		await run( importPlan, importPlan.items.filter( ( item ) => item.action !== null ).map( ( item ) => item.id ) );

		expect( wiki.writes ).toEqual( [
			'save schema:Gender Types',
			'save layout:Card',
			'save mapping:EDM',
			`create subject:${ ID_1 }`,
			`delete subject:${ ID_3 }`,
			'delete schema:Gone',
		] );
	} );

	it( 'starts each write only once the one before it has finished', async () => {
		const events: string[] = [];
		const save = wiki.saveSchema.bind( wiki );
		wiki.saveSchema = async ( name, json, comment, baseRevisionId ) => {
			events.push( `start ${ name }` );
			await new Promise( ( resolve ) => {
				setTimeout( resolve );
			} );
			await save( name, json, comment, baseRevisionId );
			events.push( `end ${ name }` );
		};

		await run( await planOn( wiki, bundleOf( [], [ newSchema( { title: 'Gender Types' } ), newSchema( { title: 'Person' } ) ] ) ) );

		expect( events ).toEqual( [ 'start Gender Types', 'end Gender Types', 'start Person', 'end Person' ] );
	} );

	it( 'does not save over a Schema edited since the preview', async () => {
		wiki.withSchemas( newSchema( { title: 'Person', description: 'Old' } ) );
		const importPlan = await planOn( wiki, bundleOf( [], [ newSchema( { title: 'Person', description: 'New' } ) ] ) );
		const edited = schemaJson( newSchema( { title: 'Person', description: 'Edited' } ) );
		await wiki.saveSchema( 'Person', edited, 'Edit', ( await wiki.getSchema( 'Person' ) ).revisionId );

		const outcome = await run( importPlan, [ itemId( 'schema', 'Person' ) ] );

		expect( results( outcome ) ).toEqual( [ 'failed Person: Edit conflict' ] );
		expect( wiki.schemas.get( 'Person' ) ).toEqual( edited );
	} );

	it( 'writes only the chosen items', async () => {
		const importPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, WOMAN ] ] ) );

		await run( importPlan, [ itemId( 'subject', ID_2 ) ] );

		expect( wiki.writes ).toEqual( [ `create subject:${ ID_2 }` ] );
	} );

	it( 'reports what it created, replaced and removed, and why a write failed, writing the rest', async () => {
		wiki.records = [ installed( [ [ ID_2, FEMALE ], [ ID_3, FEMALE ] ] ) ];
		wiki.withSubject( ID_2, FEMALE ).withSubject( ID_3, FEMALE );
		wiki.failingWrites.set( `subject:${ ID_1 }`, 'The page is protected' );

		const importPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, WOMAN ], [ 's1demo1aaaaaaa4', WOMAN ] ] ) );
		const outcome = await run( importPlan, [ ...importPlan.items.filter( ( item ) => item.selectedByDefault ).map( ( item ) => item.id ), itemId( 'subject', ID_3 ) ] );

		expect( results( outcome ) ).toEqual( [
			`failed ${ ID_1 }: The page is protected`,
			`replaced ${ ID_2 }`,
			'created s1demo1aaaaaaa4',
			`removed ${ ID_3 }`,
		] );
	} );

	it( 'gives a Subject\'s page its ID when its title was taken since the preview', async () => {
		const importPlan = await planOn( wiki, bundleOf( [ [ ID_1, subjectEntry( { pageTitle: 'Female' } ) ] ] ) );
		wiki.takenPageTitles.add( 'Female' );

		await run( importPlan );

		expect( wiki.writes ).toEqual( [ `create subject:${ ID_1 } on ${ ID_1 }` ] );
	} );

	describe( 'a Subject installed again after its removal', () => {

		const UNNAMED = subjectEntry( { label: null } );

		async function removeAndInstallAgain( subjectFirst: boolean ): Promise<ImportOutcome> {
			await run( await planOn( wiki, bundleOf( [ [ ID_1, UNNAMED ] ] ), 'Vocabularies', subjectFirst ), undefined, subjectFirst );
			await run( await planOn( wiki, bundleOf( [] ), 'Vocabularies', subjectFirst ), [ itemId( 'subject', ID_1 ) ], subjectFirst );

			return run( await planOn( wiki, bundleOf( [ [ ID_1, UNNAMED ] ] ), 'Vocabularies', subjectFirst ), undefined, subjectFirst );
		}

		it( 'goes back on the page its removal left', async () => {
			expect( results( await removeAndInstallAgain( false ) ) ).toEqual( [ `created ${ ID_1 }` ] );
			expect( wiki.writes ).toEqual( [ `create subject:${ ID_1 }`, `delete subject:${ ID_1 }`, `create subject:${ ID_1 } on ${ ID_1 }` ] );
		} );

		it( 'goes back on the page its removal left on a subject-first wiki', async () => {
			expect( results( await removeAndInstallAgain( true ) ) ).toEqual( [ `created ${ ID_1 }` ] );
		} );

	} );

	it( 'does not put a Subject on an empty page titled by its label', async () => {
		const importPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) );
		wiki.takenPageTitles.add( 'female' );
		wiki.emptyPageTitles.add( 'female' );

		await run( importPlan );

		expect( wiki.writes ).toEqual( [ `create subject:${ ID_1 } on ${ ID_1 }` ] );
	} );

	it( 'fails to create a Subject whose page title a page holds that no removal left', async () => {
		wiki.takenPageTitles.add( ID_1 );

		const outcome = await run( await planOn( wiki, bundleOf( [ [ ID_1, subjectEntry( { label: null } ) ] ] ) ) );

		expect( results( outcome ) ).toEqual( [ `failed ${ ID_1 }: A page named "${ ID_1 }" already exists` ] );
	} );

	it( 'keeps a Schema whose Subjects were not all removed', async () => {
		const gone = newSchema( { title: 'Gone' } );
		wiki.records = [ installed( [ [ ID_3, subjectEntry( { schema: 'Gone' } ) ] ], [ gone ] ) ];
		wiki.withSchemas( gone ).withSubject( ID_3, subjectEntry( { schema: 'Gone' } ) );

		const outcome = await run( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) ), [ itemId( 'schema', 'Gone' ) ] );

		expect( results( outcome ) ).toEqual( [ 'failed Gone: neowiki-bundle-remove-blocked-used' ] );
		expect( wiki.schemas.has( 'Gone' ) ).toBe( true );
	} );

	describe( 'install record', () => {

		it( 'records the bundle with the file\'s version of each item it wrote', async () => {
			await run( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) ) );

			expect( wiki.recordOf( 'Vocabularies' ) ).toMatchObject( {
				name: 'Vocabularies',
				version: '2',
				description: 'Controlled lists',
				source: 'https://example.org/vocabularies.json',
				items: [ { type: 'subject', key: ID_1, hash: hashOf( FEMALE ), label: 'female', schema: 'Gender Types' } ],
			} );
		} );

		it( 'records a Schema it wrote whole, as the bundle has it', async () => {
			const genderTypes = newSchema( { title: 'Gender Types' } );

			await run( await planOn( wiki, bundleOf( [], [ genderTypes ] ) ) );

			expect( wiki.recordOf( 'Vocabularies' )?.items ).toEqual( [ { type: 'schema', key: 'Gender Types', item: schemaItem( schemaJson( genderTypes ) ) } ] );
		} );

		it( 'records what it left as it was with the file\'s version, so that it shows as changed here', async () => {
			wiki.withSubject( ID_1, WOMAN );

			await run( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) ) );

			expect( recordedHashes() ).toEqual( { [ ID_1 ]: hashOf( FEMALE ) } );
		} );

		it( 'leaves out what it did not create', async () => {
			const importPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, WOMAN ] ] ) );

			await run( importPlan, [ itemId( 'subject', ID_2 ) ] );

			expect( Object.keys( recordedHashes() ) ).toEqual( [ ID_2 ] );
		} );

		it( 'reports a refused Layout save as failed, and leaves the Layout out', async () => {
			wiki.failingWrites.set( 'layout:Gender card', 'The page is protected' );

			const outcome = await run( await planOn( wiki, bundleOf( [], [], {
				layouts: new Map( [ [ 'Gender card', { schema: 'Gender Types', type: 'infobox' } ] ] ),
			} ) ) );

			expect( results( outcome ) ).toEqual( [ 'failed Gender card: The page is protected' ] );
			expect( recordedHashes() ).toEqual( {} );
		} );

		it( 'keeps the installed version of an item whose update failed', async () => {
			wiki.records = [ installed( [ [ ID_1, FEMALE ] ] ) ];
			wiki.withSubject( ID_1, FEMALE );
			wiki.failingWrites.set( `subject:${ ID_1 }`, 'The page is protected' );

			await run( await planOn( wiki, bundleOf( [ [ ID_1, WOMAN ] ] ) ) );

			expect( recordedHashes() ).toEqual( { [ ID_1 ]: hashOf( FEMALE ) } );
		} );

		it( 'lets go of what the bundle no longer has, removed or not', async () => {
			wiki.records = [ installed( [ [ ID_2, FEMALE ], [ ID_3, FEMALE ] ] ) ];
			wiki.withSubject( ID_2, FEMALE ).withSubject( ID_3, FEMALE );

			const importPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) );
			await run( importPlan, [ itemId( 'subject', ID_1 ), itemId( 'subject', ID_2 ) ] );

			expect( Object.keys( recordedHashes() ) ).toEqual( [ ID_1 ] );
		} );

		it( 'lets go of what the bundle no longer has when its removal fails', async () => {
			wiki.records = [ installed( [ [ ID_2, FEMALE ] ] ) ];
			wiki.withSubject( ID_2, FEMALE );
			wiki.failingWrites.set( `subject:${ ID_2 }`, 'The page is protected' );

			await run( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) ), [ itemId( 'subject', ID_1 ), itemId( 'subject', ID_2 ) ] );

			expect( Object.keys( recordedHashes() ) ).toEqual( [ ID_1 ] );
		} );

		it( 'leaves out an item in conflict that it had not recorded', async () => {
			wiki.withSubject( ID_1, subjectEntry( { schema: 'Person' } ) );

			await run( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, WOMAN ] ] ) ) );

			expect( Object.keys( recordedHashes() ) ).toEqual( [ ID_2 ] );
		} );

		it( 'keeps what this wiki deleted, with the file\'s version', async () => {
			wiki.records = [ installed( [ [ ID_1, FEMALE ] ] ) ];

			await run( await planOn( wiki, bundleOf( [ [ ID_1, WOMAN ] ] ) ) );

			expect( recordedHashes() ).toEqual( { [ ID_1 ]: hashOf( WOMAN ) } );
		} );

		it( 'reports a record it could not save', async () => {
			wiki.failingWrites.set( 'record', 'You do not have permission' );

			const outcome = await run( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) ) );

			expect( outcome.record ).toEqual( { saved: false, reason: 'You do not have permission' } );
		} );

	} );

} );
