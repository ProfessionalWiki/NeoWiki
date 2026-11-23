import { beforeEach, describe, expect, it } from 'vitest';
import { defaultChoice, type InstallPlan } from '../../src/application/installPlan';
import { newSchema } from '@/TestHelpers';
import { setupMwMock } from '../../../tests/VueTestHelpers';
import { InMemoryDataModel } from './InMemoryDataModel';
import { bundleOf, installed, planAllOn, planOn, subjectEntry } from './builders';

const ID_1 = 's1demo1aaaaaaa1';
const ID_2 = 's1demo1aaaaaaa2';
const ID_3 = 's1demo1aaaaaaa3';
const FEMALE = subjectEntry( { label: 'female' } );
const FEMALE_EDITED = subjectEntry( { label: 'female (edited)' } );
const WOMAN = subjectEntry( { label: 'woman' } );
const BIRTH = newSchema( { title: 'Birth', subjectsAreDependent: true } );
const ADA = subjectEntry( { schema: 'Person', label: 'Ada', dependentSubjects: [ { id: ID_2, schema: 'Birth', statements: {} } ] } );

let wiki: InMemoryDataModel;

/**
 * What the preview shows of an item: its status, what installing it does, and whether it starts ticked.
 */
function row( installPlan: InstallPlan, key: string ): [ string, string | null, boolean ] {
	const item = installPlan.items.find( ( candidate ) => candidate.key === key );

	expect( item ).toBeDefined();
	return [ item!.status, item!.action, defaultChoice( installPlan ).has( item!.id ) ];
}

function reasonOf( installPlan: InstallPlan, key: string ): string | null {
	return installPlan.items.find( ( item ) => item.key === key )?.reason ?? null;
}

describe( 'planInstalls', () => {

	beforeEach( () => {
		setupMwMock();
		wiki = new InMemoryDataModel();
	} );

	describe( 'installing', () => {

		it( 'creates what the wiki does not have, ticked', async () => {
			expect( row( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) ), ID_1 ) ).toEqual( [ 'new', 'create', true ] );
		} );

		it( 'leaves what the wiki has as the file does', async () => {
			wiki.withSubject( ID_1, FEMALE );

			expect( row( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) ), ID_1 ) ).toEqual( [ 'unchanged', null, false ] );
		} );

		it( 'leaves a Subject whose page the file titles otherwise', async () => {
			wiki.withSubject( ID_1, FEMALE );

			expect( row( await planOn( wiki, bundleOf( [ [ ID_1, { ...FEMALE, pageTitle: 'Female' } ] ] ) ), ID_1 ) )
				.toEqual( [ 'unchanged', null, false ] );
		} );

		it( 'offers to replace what the wiki has otherwise, unticked, with both versions', async () => {
			wiki.withSubject( ID_1, FEMALE_EDITED );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) );

			expect( row( installPlan, ID_1 ) ).toEqual( [ 'differs', 'replace', false ] );
			expect( installPlan.items[ 0 ].versionHere ).toContain( 'female (edited)' );
			expect( installPlan.items[ 0 ].versionUpstream ).toContain( '"female"' );
		} );

		it( 'tells relation IDs apart from content', async () => {
			wiki.withSubject( ID_1, subjectEntry( {
				statements: { Metatype: { propertyType: 'relation', value: [ { id: 'r1demo1rrrrrrr1', target: ID_2 } ] } },
			} ) );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, subjectEntry( {
				statements: { Metatype: { propertyType: 'relation', value: [ { target: ID_2 } ] } },
			} ) ] ] ) );

			expect( row( installPlan, ID_1 ) ).toEqual( [ 'unchanged', null, false ] );
		} );

		it( 'refuses a Subject whose ID a Subject of another Schema has', async () => {
			wiki.withSubject( ID_1, subjectEntry( { schema: 'Person' } ) );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) );

			expect( row( installPlan, ID_1 ) ).toEqual( [ 'conflict', null, false ] );
			expect( reasonOf( installPlan, ID_1 ) ).toBe( 'neowiki-bundle-conflict-subject-schemaPerson' );
		} );

		it( 'refuses a Subject whose Dependent Subject ID another Subject holds', async () => {
			wiki.withSubject( ID_3, subjectEntry( { schema: 'Person', dependentSubjects: [ { id: ID_2, schema: 'Birth', statements: {} } ] } ) );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, subjectEntry( {
				schema: 'Person',
				dependentSubjects: [ { id: ID_2, schema: 'Birth', statements: {} } ],
			} ) ] ] ) );

			expect( reasonOf( installPlan, ID_1 ) ).toBe( `neowiki-bundle-conflict-id-in-use${ ID_2 }` );
		} );

		it( 'refuses a Subject whose Dependent Subject it holds is of another Schema on the wiki', async () => {
			wiki.withSubject( ID_1, subjectEntry( { schema: 'Person', dependentSubjects: [ { id: ID_2, schema: 'Death', statements: {} } ] } ) );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, subjectEntry( {
				schema: 'Person',
				dependentSubjects: [ { id: ID_2, schema: 'Birth', statements: {} } ],
			} ) ] ] ) );

			expect( reasonOf( installPlan, ID_1 ) ).toBe( `neowiki-bundle-conflict-id-in-use${ ID_2 }` );
		} );

		it( 'replaces the Dependent Subjects a Subject holds already', async () => {
			wiki.withSchemas( BIRTH );
			wiki.withSubject( ID_1, subjectEntry( {
				schema: 'Person',
				dependentSubjects: [ { id: ID_2, schema: 'Birth', statements: { Year: { propertyType: 'number', value: 1900 } } } ],
			} ) );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, subjectEntry( {
				schema: 'Person',
				dependentSubjects: [ { id: ID_2, schema: 'Birth', statements: { Year: { propertyType: 'number', value: 1901 } } } ],
			} ) ] ] ) );

			expect( row( installPlan, ID_1 ) ).toEqual( [ 'differs', 'replace', false ] );
		} );

		it( 'refuses a Subject whose Dependent Subjects are of a Schema the wiki lacks', async () => {
			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, ADA ] ] ) );

			expect( row( installPlan, ID_1 ) ).toEqual( [ 'conflict', null, false ] );
			expect( reasonOf( installPlan, ID_1 ) ).toBe( 'neowiki-bundle-conflict-dependent-schemaBirth' );
		} );

		it( 'refuses a Subject whose Dependent Subjects are of a Schema of standalone Subjects', async () => {
			wiki.withSchemas( newSchema( { title: 'Birth', subjectsAreDependent: false } ) );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, ADA ] ] ) );

			expect( reasonOf( installPlan, ID_1 ) ).toBe( 'neowiki-bundle-conflict-dependent-schemaBirth' );
		} );

		it( 'creates a Subject whose Dependent Subjects are of a Schema the file creates', async () => {
			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, ADA ] ], [ BIRTH ] ) );

			expect( row( installPlan, ID_1 ) ).toEqual( [ 'new', 'create', true ] );
		} );

		it( 'refuses a Subject whose ID is a Dependent Subject of another', async () => {
			wiki.withSubject( ID_3, subjectEntry( { schema: 'Person', dependentSubjects: [ { id: ID_1, schema: 'Gender Types', statements: {} } ] } ) );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) );

			expect( reasonOf( installPlan, ID_1 ) ).toBe( `neowiki-bundle-conflict-id-in-use${ ID_1 }` );
		} );

		it( 'refuses a Schema that would change whether its Subjects are dependent', async () => {
			wiki.withSchemas( newSchema( { title: 'Birth', subjectsAreDependent: false } ) );

			const installPlan = await planOn( wiki, bundleOf( [], [ newSchema( { title: 'Birth', subjectsAreDependent: true } ) ] ) );

			expect( reasonOf( installPlan, 'Birth' ) ).toBe( 'neowiki-bundle-conflict-dependence' );
		} );

		it( 'refuses a Schema that would make its dependent Subjects standalone', async () => {
			wiki.withSchemas( BIRTH );

			const installPlan = await planOn( wiki, bundleOf( [], [ newSchema( { title: 'Birth', subjectsAreDependent: false } ) ] ) );

			expect( reasonOf( installPlan, 'Birth' ) ).toBe( 'neowiki-bundle-conflict-dependence' );
		} );

		it( 'refuses an item another installed bundle owns', async () => {
			wiki.records = [ installed( [ [ ID_1, FEMALE ] ], [], { name: 'Genders' } ) ];

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) );

			expect( reasonOf( installPlan, ID_1 ) ).toBe( 'neowiki-bundle-conflict-ownedGenders' );
		} );

		it( 'refuses an item whose version on the wiki cannot be read', async () => {
			wiki.unreadable.add( `subject:${ ID_1 }` );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) );

			expect( reasonOf( installPlan, ID_1 ) ).toBe( `neowiki-bundle-conflict-unreadableCannot read subject:${ ID_1 }` );
		} );

	} );

	describe( 'installing several bundles together', () => {

		const MODEL = { name: 'Model', version: '1', description: '' };
		const PEOPLE = { name: 'People', version: '1', description: '' };

		it( 'counts what an earlier bundle provides as present', async () => {
			const plans = await planAllOn( wiki, [
				bundleOf( [], [ BIRTH ], { metadata: MODEL } ),
				bundleOf( [ [ ID_1, ADA ] ], [], { metadata: PEOPLE } ),
			] );

			expect( row( plans[ 1 ], ID_1 ) ).toEqual( [ 'new', 'create', true ] );
		} );

		it( 'refuses in a later bundle what an earlier one has', async () => {
			const plans = await planAllOn( wiki, [
				bundleOf( [ [ ID_1, FEMALE ] ], [], { metadata: MODEL } ),
				bundleOf( [ [ ID_1, FEMALE ] ], [], { metadata: PEOPLE } ),
			] );

			expect( reasonOf( plans[ 1 ], ID_1 ) ).toBe( 'neowiki-bundle-conflict-ownedModel' );
		} );

	} );

	describe( 'updating', () => {

		beforeEach( () => {
			wiki.records = [ installed( [ [ ID_1, FEMALE ] ] ) ];
		} );

		it( 'offers what an earlier install left unticked as not installed, unticked', async () => {
			wiki.records = [ installed( [ [ ID_1, FEMALE ] ], [], { declined: [ { type: 'subject', key: ID_2 } ] } ) ];
			wiki.withSubject( ID_1, FEMALE );

			expect( row( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, WOMAN ] ] ) ), ID_2 ) ).toEqual( [ 'declined', 'create', false ] );
		} );

		it( 'replaces what the bundle changed and this wiki did not, ticked', async () => {
			wiki.withSubject( ID_1, FEMALE );

			expect( row( await planOn( wiki, bundleOf( [ [ ID_1, WOMAN ] ] ) ), ID_1 ) ).toEqual( [ 'changed-upstream', 'replace', true ] );
		} );

		it( 'offers to replace what both changed, unticked, with both versions', async () => {
			wiki.withSubject( ID_1, FEMALE_EDITED );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, WOMAN ] ] ) );

			expect( row( installPlan, ID_1 ) ).toEqual( [ 'changed-both', 'replace', false ] );
			expect( installPlan.items[ 0 ].versionHere ).toContain( 'female (edited)' );
			expect( installPlan.items[ 0 ].versionUpstream ).toContain( 'woman' );
		} );

		it( 'keeps what only this wiki changed', async () => {
			wiki.withSubject( ID_1, FEMALE_EDITED );

			expect( row( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) ), ID_1 ) ).toEqual( [ 'changed-here', null, false ] );
		} );

		it( 'leaves what neither changed', async () => {
			wiki.withSubject( ID_1, FEMALE );

			expect( row( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) ), ID_1 ) ).toEqual( [ 'unchanged', null, false ] );
		} );

		it( 'leaves what both changed alike', async () => {
			wiki.withSubject( ID_1, WOMAN );

			expect( row( await planOn( wiki, bundleOf( [ [ ID_1, WOMAN ] ] ) ), ID_1 ) ).toEqual( [ 'unchanged', null, false ] );
		} );

		it( 'treats what the bundle added as on an install', async () => {
			wiki.withSubject( ID_1, FEMALE ).withSubject( ID_3, FEMALE_EDITED );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, WOMAN ], [ ID_3, FEMALE ] ] ) );

			expect( row( installPlan, ID_2 ) ).toEqual( [ 'new', 'create', true ] );
			expect( row( installPlan, ID_3 ) ).toEqual( [ 'differs', 'replace', false ] );
		} );

		it( 'offers to re-create, unticked, what this wiki deleted and the bundle changed', async () => {
			expect( row( await planOn( wiki, bundleOf( [ [ ID_1, WOMAN ] ] ) ), ID_1 ) ).toEqual( [ 'deleted-here', 'create', false ] );
		} );

		it( 'leaves deleted what this wiki deleted and the bundle did not change', async () => {
			expect( row( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) ), ID_1 ) ).toEqual( [ 'deleted-here', null, false ] );
		} );

		it( 'offers to remove, unticked, what the bundle no longer has', async () => {
			wiki.withSubject( ID_1, FEMALE );

			expect( row( await planOn( wiki, bundleOf( [], [ newSchema( { title: 'Gender Types' } ) ] ) ), ID_1 ) )
				.toEqual( [ 'removed-upstream', 'remove', false ] );
		} );

		it( 'lists nothing for what the bundle no longer has and this wiki deleted', async () => {
			const installPlan = await planOn( wiki, bundleOf( [], [ newSchema( { title: 'Gender Types' } ) ] ) );

			expect( installPlan.items.map( ( item ) => item.key ) ).toEqual( [ 'Gender Types' ] );
		} );

		it( 'does not offer to remove a Subject that Subjects outside the bundle point at', async () => {
			wiki.withSubject( ID_1, FEMALE ).withSubject( ID_3, subjectEntry( { schema: 'Person' } ) );
			wiki.referencingSubjects.set( ID_1, [ ID_3 ] );

			const installPlan = await planOn( wiki, bundleOf( [], [ newSchema( { title: 'Gender Types' } ) ] ) );

			expect( row( installPlan, ID_1 ) ).toEqual( [ 'removed-upstream', null, false ] );
			expect( reasonOf( installPlan, ID_1 ) ).toBe( 'neowiki-bundle-remove-blocked-referenced' );
		} );

		it( 'does not offer to remove what the bundle no longer has when its version on the wiki cannot be read', async () => {
			wiki.withSubject( ID_1, FEMALE );
			wiki.unreadable.add( `subject:${ ID_1 }` );

			const installPlan = await planOn( wiki, bundleOf( [], [ newSchema( { title: 'Gender Types' } ) ] ) );

			expect( reasonOf( installPlan, ID_1 ) ).toBe( `neowiki-bundle-remove-blocked-unreadableCannot read subject:${ ID_1 }` );
		} );

		it( 'offers to remove a Subject that only Subjects of the bundle point at', async () => {
			wiki.withSubject( ID_1, FEMALE ).withSubject( ID_2, WOMAN );
			wiki.referencingSubjects.set( ID_1, [ ID_2 ] );

			expect( row( await planOn( wiki, bundleOf( [ [ ID_2, WOMAN ] ] ) ), ID_1 ) ).toEqual( [ 'removed-upstream', 'remove', false ] );
		} );

		it( 'offers to remove Subjects the bundle no longer has that point at each other', async () => {
			wiki.records = [ installed( [ [ ID_1, FEMALE ], [ ID_2, WOMAN ] ] ) ];
			wiki.withSubject( ID_1, FEMALE ).withSubject( ID_2, WOMAN );
			wiki.referencingSubjects.set( ID_1, [ ID_2 ] );

			const installPlan = await planOn( wiki, bundleOf( [], [ newSchema( { title: 'Gender Types' } ) ] ) );

			expect( [ row( installPlan, ID_1 ), row( installPlan, ID_2 ) ] )
				.toEqual( [ [ 'removed-upstream', 'remove', false ], [ 'removed-upstream', 'remove', false ] ] );
		} );

		it( 'does not offer to remove a Subject whose list of referencing Subjects was cut short', async () => {
			wiki.withSubject( ID_1, FEMALE ).withSubject( ID_2, WOMAN );
			wiki.referencingSubjects.set( ID_1, [ ID_2 ] );
			wiki.truncatedReferences.add( ID_1 );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_2, WOMAN ] ] ) );

			expect( reasonOf( installPlan, ID_1 ) ).toBe( 'neowiki-bundle-remove-blocked-referenced' );
		} );

		it( 'does not offer to remove a Schema that Subjects outside the bundle use', async () => {
			const genderTypes = newSchema( { title: 'Gender Types' } );
			wiki.records = [ installed( [ [ ID_1, FEMALE ] ], [ genderTypes ] ) ];
			wiki.withSchemas( genderTypes ).withSubject( ID_1, FEMALE ).withSubject( ID_3, WOMAN );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_2, subjectEntry( { schema: 'Other' } ) ] ] ) );

			expect( row( installPlan, 'Gender Types' ) ).toEqual( [ 'removed-upstream', null, false ] );
			expect( reasonOf( installPlan, 'Gender Types' ) ).toBe( 'neowiki-bundle-remove-blocked-used' );
		} );

		it( 'offers to remove a Schema whose Subjects are all removed with it', async () => {
			const genderTypes = newSchema( { title: 'Gender Types' } );
			wiki.records = [ installed( [ [ ID_1, FEMALE ] ], [ genderTypes ] ) ];
			wiki.withSchemas( genderTypes ).withSubject( ID_1, FEMALE );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_2, subjectEntry( { schema: 'Other' } ) ] ] ) );

			expect( row( installPlan, 'Gender Types' ) ).toEqual( [ 'removed-upstream', 'remove', false ] );
		} );

		it( 'offers to remove a dependent Schema whose Host Subjects are all removed', async () => {
			wiki.records = [ installed( [ [ ID_1, ADA ] ], [ BIRTH ] ) ];
			wiki.withSchemas( BIRTH ).withSubject( ID_1, ADA );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_3, subjectEntry( { schema: 'Other' } ) ] ] ) );

			expect( row( installPlan, 'Birth' ) ).toEqual( [ 'removed-upstream', 'remove', false ] );
		} );

		it( 'does not offer to remove a dependent Schema whose Host Subject stays', async () => {
			wiki.records = [ installed( [ [ ID_1, ADA ] ], [ BIRTH ] ) ];
			wiki.withSchemas( BIRTH ).withSubject( ID_1, ADA );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, ADA ] ] ) );

			expect( row( installPlan, 'Birth' ) ).toEqual( [ 'removed-upstream', null, false ] );
		} );

		it( 'offers to remove a Subject that only a bundle Subject\'s Dependent Subject points at', async () => {
			wiki.records = [ installed( [ [ ID_1, FEMALE ], [ ID_3, ADA ] ] ) ];
			wiki.withSubject( ID_1, FEMALE ).withSubject( ID_3, ADA );
			wiki.referencingSubjects.set( ID_1, [ ID_2 ] );

			expect( row( await planOn( wiki, bundleOf( [ [ ID_3, ADA ] ] ) ), ID_1 ) ).toEqual( [ 'removed-upstream', 'remove', false ] );
		} );

		it( 'does not offer to remove a Subject that a Dependent Subject outside the bundle points at', async () => {
			wiki.withSubject( ID_1, FEMALE ).withSubject( ID_3, ADA );
			wiki.referencingSubjects.set( ID_1, [ ID_2 ] );

			expect( row( await planOn( wiki, bundleOf( [], [ newSchema( { title: 'Gender Types' } ) ] ) ), ID_1 ) )
				.toEqual( [ 'removed-upstream', null, false ] );
		} );

		it( 'finds the installed bundle under another spelling of its name', async () => {
			wiki.withSubject( ID_1, FEMALE );

			expect( row( await planOn( wiki, bundleOf( [ [ ID_1, WOMAN ] ] ), 'Vocabularies_' ), ID_1 ) )
				.toEqual( [ 'changed-upstream', 'replace', true ] );
		} );

	} );

	describe( 'page titles', () => {

		it( 'keeps the title the file names when it is free', async () => {
			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, subjectEntry( { pageTitle: 'Female' } ) ] ] ) );

			expect( installPlan.items[ 0 ] ).toMatchObject( { pageTitle: 'Female', pageTitleTaken: false } );
		} );

		it( 'gives the page the Subject\'s ID when the title the file names is taken', async () => {
			wiki.takenPageTitles.add( 'Female' );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, subjectEntry( { pageTitle: 'Female' } ) ] ] ) );

			expect( installPlan.items[ 0 ] ).toMatchObject( { pageTitle: ID_1, pageTitleTaken: true } );
		} );

		it( 'gives the page the Subject\'s ID when the title its label gives is taken', async () => {
			wiki.takenPageTitles.add( 'female' );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) );

			expect( installPlan.items[ 0 ] ).toMatchObject( { pageTitle: ID_1, pageTitleTaken: true } );
		} );

		it( 'leaves titles to a subject-first wiki', async () => {
			wiki.takenPageTitles.add( 'Female' );

			const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, subjectEntry( { pageTitle: 'Female' } ) ] ] ), 'Vocabularies', true );

			expect( installPlan.items[ 0 ] ).toMatchObject( { pageTitle: null, pageTitleTaken: false } );
		} );

	} );

} );
