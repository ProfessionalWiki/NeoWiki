import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, inject, ref } from 'vue';
import { useDependentSubjectEditing, type UseDependentSubjectEditing } from '@/composables/useDependentSubjectEditing.ts';
import { DependentSubjectEditingKey, type DependentSubjectEditing } from '@/components/SubjectEditor/DependentSubjectEditing.ts';
import { DependentSubjectLoader } from '@/application/DependentSubjectLoader.ts';
import { InMemorySchemaLookup } from '@/application/SchemaLookup.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { newRelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { newSchema, newSubject } from '@/TestHelpers.ts';
import { setupMwMock } from '../VueTestHelpers.ts';

const MINTED_ID = 's1mintedAAAAAA1';
const DRAFT_ID = 's1draftAAAAAAA1';

const birthSchema = newSchema( { title: 'Birth', subjectsAreDependent: true } );
const placeSchema = newSchema( { title: 'Place' } );
const SCHEMAS = { subjects: [], schemas: [ birthSchema, placeSchema ] };

interface Mounted {
	composable: UseDependentSubjectEditing;
	editing: DependentSubjectEditing;
}

function mountEditor(
	addingAllowed: () => boolean = () => true,
	mintSubjectId: () => Promise<SubjectId> = () => Promise.resolve( new SubjectId( MINTED_ID ) ),
): Mounted {
	const mounted = {} as Mounted;
	const loader = new DependentSubjectLoader(
		{ getPageSubjects: vi.fn(), mintSubjectId },
		new InMemorySchemaLookup( [ birthSchema, placeSchema ] ),
		async () => [],
	);

	const Field = {
		setup() {
			mounted.editing = inject( DependentSubjectEditingKey ) as DependentSubjectEditing;
			return {};
		},
		template: '<div></div>',
	};

	mount( defineComponent( {
		components: { Field },
		setup() {
			mounted.composable = useDependentSubjectEditing( loader, addingAllowed, ref( [ DRAFT_ID ] ) );
			mounted.composable.startFrom( SCHEMAS, false );
			return {};
		},
		template: '<Field />',
	} ) );

	return mounted;
}

describe( 'useDependentSubjectEditing', () => {

	beforeEach( () => {
		setupMwMock( { functions: [ 'msg', 'notify' ] } );
	} );

	it( 'tells a property whose target Schemas are dependent from one whose are standalone', () => {
		const { editing } = mountEditor();

		expect( editing.targetsDependentSubjects( newRelationProperty( { targetSchemas: [ 'Birth' ] } ) ) ).toBe( true );
		expect( editing.targetsDependentSubjects( newRelationProperty( { targetSchemas: [ 'Place' ] } ) ) ).toBe( false );
	} );

	it( 'serves the Dependent Subjects it starts from as ones the wiki holds', () => {
		const birth = newSubject( { id: 's1birthAAAAAAA1', label: null, schemaName: 'Birth' } );
		const { composable, editing } = mountEditor();

		composable.startFrom( { subjects: [ birth ], schemas: [ birthSchema ] }, false );

		expect( editing.subject( 's1birthAAAAAAA1' ) ).toEqual( birth );
		expect( editing.isNew( 's1birthAAAAAAA1' ) ).toBe( false );
	} );

	it( 'adds a Dependent Subject the wiki does not hold, unsaved until it is written', async () => {
		const { composable, editing } = mountEditor();

		const added = await editing.add( 'Birth' );

		expect( editing.subject( MINTED_ID ) ).toEqual( added );
		expect( composable.unsavedTargetIds.value ).toContain( MINTED_ID );

		composable.markWritten( [ added! ] );

		expect( composable.unsavedTargetIds.value ).not.toContain( MINTED_ID );
		expect( editing.isNew( MINTED_ID ) ).toBe( false );
	} );

	it( 'counts the other Subjects the editor holds unsaved among the unsaved targets', async () => {
		const { editing } = mountEditor();

		await editing.add( 'Birth' );

		expect( editing.unsavedTargetIds() ).toEqual( expect.arrayContaining( [ DRAFT_ID, MINTED_ID ] ) );
	} );

	it( 'adds nothing while adding is closed, and says so', async () => {
		const { editing } = mountEditor( () => false );

		const added = await editing.add( 'Birth' );

		expect( added ).toBeNull();
		expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-dependent-subject-add-error', { type: 'error' } );
	} );

	it( 'drops an addition that lands after the editor started over, quietly', async () => {
		let mint!: ( id: SubjectId ) => void;
		const { composable, editing } = mountEditor(
			() => true,
			() => new Promise( ( resolve ) => {
				mint = resolve;
			} ),
		);

		const adding = editing.add( 'Birth' );
		composable.startFrom( SCHEMAS, false );
		mint( new SubjectId( MINTED_ID ) );

		expect( await adding ).toBeNull();
		expect( editing.subject( MINTED_ID ) ).toBeUndefined();
		expect( mw.notify ).not.toHaveBeenCalled();
	} );

	it( 'drops an addition that lands after the save started, quietly', async () => {
		let addingAllowed = true;
		let mint!: ( id: SubjectId ) => void;
		const { editing } = mountEditor(
			() => addingAllowed,
			() => new Promise( ( resolve ) => {
				mint = resolve;
			} ),
		);

		const adding = editing.add( 'Birth' );
		addingAllowed = false;
		mint( new SubjectId( MINTED_ID ) );

		expect( await adding ).toBeNull();
		expect( editing.subject( MINTED_ID ) ).toBeUndefined();
		expect( mw.notify ).not.toHaveBeenCalled();
	} );

	it( 'adds the Dependent Subjects the one it adds starts with', async () => {
		const requiring = newSchema( {
			title: 'Birth',
			subjectsAreDependent: true,
			properties: new PropertyDefinitionList( [
				newRelationProperty( { name: 'Record', targetSchemas: [ 'Record' ], required: true } ),
			] ),
		} );
		const record = newSchema( { title: 'Record', subjectsAreDependent: true } );
		const ids = [ MINTED_ID, 's1recordAAAAAA1' ];
		const { composable, editing } = mountEditor( () => true, () => Promise.resolve( new SubjectId( ids.shift() as string ) ) );
		composable.startFrom( { subjects: [], schemas: [ requiring, record ] }, false );

		await editing.add( 'Birth' );

		expect( editing.subject( 's1recordAAAAAA1' )?.getSchemaName() ).toBe( 'Record' );
	} );

} );
