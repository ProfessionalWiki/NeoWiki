import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { defineComponent, provide, ref, type Ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import DependentSubjectsInput from '@/components/SubjectEditor/DependentSubjectsInput.vue';
import { DependentSubjectViolationsKey } from '@/components/SubjectEditor/DependentSubjectEditing.ts';
import { useDependentSubjectEditing } from '@/composables/useDependentSubjectEditing.ts';
import { DependentSubjectLoader } from '@/application/DependentSubjectLoader.ts';
import { InMemorySchemaLookup } from '@/application/SchemaLookup.ts';
import type { DependentSubjectsInputExposes } from '@/components/SubjectEditor/DependentSubjectsInput.vue';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { newRelationProperty, RelationType, type RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { newTextProperty, TextType } from '@/domain/propertyTypes/Text.ts';
import { newNumberProperty } from '@/domain/propertyTypes/Number.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { Statement } from '@/domain/Statement.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { newRelation, newStringValue, RelationValue, type Value } from '@/domain/Value.ts';
import type { Subject } from '@/domain/Subject.ts';
import type { Schema } from '@/domain/Schema.ts';
import type { SubjectViolation } from '@/domain/SubjectViolation.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { newSchema, newSubject } from '@/TestHelpers.ts';
import { NeoWikiTestServices } from '../../NeoWikiTestServices.ts';
import {
	accessibleName,
	createI18nMock,
	describedAs,
	reportUnparseableNumber,
	setupMwMock,
} from '../../VueTestHelpers.ts';

// Focus is read from the document, where a group an earlier test left mounted would still be.
enableAutoUnmount( afterEach );

const PAGE = new PageIdentifiers( 1, 'Pablo Picasso' );
const BIRTH_ID = 's1birthAAAAAAA1';
const RECORD_ID = 's1recordAAAAAA1';
const NAME_ID = 's1nameAAAAAAAA1';
const SECOND_NAME_ID = 's1nameAAAAAAAA2';
// What the fake repository mints, in order.
const MINTED_ID = 's1mintedAAAAAA1';

const recordSchema = newSchema( {
	title: 'Record',
	subjectsAreDependent: true,
	properties: new PropertyDefinitionList( [ newTextProperty( { name: 'Archive' } ) ] ),
} );
const birthSchema = newSchema( {
	title: 'Birth',
	subjectsAreDependent: true,
	properties: new PropertyDefinitionList( [
		newTextProperty( { name: 'Date' } ),
		newNumberProperty( { name: 'Weight' } ),
		newRelationProperty( { name: 'Record', targetSchemas: [ 'Record' ] } ),
	] ),
} );
const nameSchema = newSchema( {
	title: 'Name',
	subjectsAreDependent: true,
	properties: new PropertyDefinitionList( [ newTextProperty( { name: 'Text' } ) ] ),
} );
const pseudonymSchema = newSchema( {
	title: 'Pseudonym',
	subjectsAreDependent: true,
	properties: new PropertyDefinitionList( [ newTextProperty( { name: 'Text' } ) ] ),
} );

const SCHEMAS = [ recordSchema, birthSchema, nameSchema, pseudonymSchema ];

const birthProperty = newRelationProperty( { name: 'Birth', targetSchemas: [ 'Birth' ] } );
const namesProperty = newRelationProperty( { name: 'Names', targetSchemas: [ 'Name', 'Pseudonym' ], multiple: true } );

function statementsOf( values: Record<string, Value> ): StatementList {
	return new StatementList( Object.entries( values ).map( ( [ property, value ] ) => new Statement(
		new PropertyName( property ),
		value instanceof RelationValue ? RelationType.typeName : TextType.typeName,
		value,
	) ) );
}

function pointingAt( ...targetIds: string[] ): RelationValue {
	return new RelationValue( targetIds.map( ( id ) => newRelation( undefined, id ) ) );
}

const storedBirth = newSubject( {
	id: BIRTH_ID,
	label: null,
	schemaName: 'Birth',
	pageIdentifiers: PAGE,
	statements: statementsOf( { Date: newStringValue( '25 October 1881' ), Record: pointingAt( RECORD_ID ) } ),
} );
const storedRecord = newSubject( {
	id: RECORD_ID,
	label: null,
	schemaName: 'Record',
	pageIdentifiers: PAGE,
	statements: statementsOf( { Archive: newStringValue( 'Archivo Municipal' ) } ),
} );

function storedName( id: string, text: string, schemaName = 'Name' ): Subject {
	return newSubject( { id, label: null, schemaName, pageIdentifiers: PAGE, statements: statementsOf( { Text: newStringValue( text ) } ) } );
}

interface MountOptions {
	property?: RelationProperty;
	value?: Value;
	stored?: Subject[];
	schemas?: Schema[];
	serverViolations?: SubjectViolation[];
	savedViolations?: Ref<readonly SubjectViolation[]>;
	draftIds?: string[];
	mintSubjectId?: () => Promise<SubjectId>;
}

let validateSubject: Mock;
let validateSubjectUpdate: Mock;

function mountInput( {
	property = birthProperty,
	value = undefined,
	stored = [],
	schemas = SCHEMAS,
	serverViolations = [],
	savedViolations = ref( [] ),
	draftIds = [],
	mintSubjectId = mintingInOrder(),
}: MountOptions = {} ): VueWrapper {
	const loader = new DependentSubjectLoader(
		{ getPageSubjects: vi.fn(), mintSubjectId },
		new InMemorySchemaLookup( SCHEMAS ),
		async () => [],
	);

	const Host = defineComponent( {
		components: { DependentSubjectsInput },
		setup() {
			useDependentSubjectEditing( loader, () => true, ref( draftIds ) )
				.startFrom( { subjects: stored, schemas }, false );
			provide( DependentSubjectViolationsKey, savedViolations );

			return { property, value, serverViolations };
		},
		template: '<DependentSubjectsInput ref="input" :label="property.name.toString()" :property="property" ' +
			':model-value="value" :server-violations="serverViolations" />',
	} );

	return mount( Host, {
		attachTo: document.body,
		global: {
			provide: NeoWikiTestServices.getServices(),
			directives: { tooltip: {} },
			mocks: { $i18n: createI18nMock() },
		},
	} );
}

function mintingInOrder(): () => Promise<SubjectId> {
	const unminted = [ MINTED_ID, 's1mintedAAAAAA2', 's1mintedAAAAAA3' ];

	return async () => new SubjectId( unminted.shift() as string );
}

// Holds the ids back until the test releases them.
function mintingOnRelease(): { mintSubjectId: () => Promise<SubjectId>; release: () => void } {
	const held: ( () => void )[] = [];
	const mint = mintingInOrder();

	return {
		mintSubjectId: () => new Promise( ( resolve ) => {
			held.push( () => resolve( mint() ) );
		} ),
		release: () => held.splice( 0 ).forEach( ( resolveHeld ) => resolveHeld() ),
	};
}

function input( wrapper: VueWrapper ): VueWrapper {
	return wrapper.findComponent( DependentSubjectsInput );
}

// Through the host's template ref, which carries what the input exposes; a found component's vm does not.
function exposed( wrapper: VueWrapper ): Required<DependentSubjectsInputExposes> {
	return wrapper.vm.$refs.input as Required<DependentSubjectsInputExposes>;
}

function groups( wrapper: VueWrapper ): DOMWrapper<Element>[] {
	return wrapper.findAll( '.ext-neowiki-dependent-subject-editor' );
}

function fieldNamed( within: VueWrapper | DOMWrapper<Element>, name: string ): DOMWrapper<HTMLInputElement> {
	const field = within.findAll( '.cdx-field' )
		.filter( ( candidate ) => candidate.isVisible() )
		.find( ( candidate ) => candidate.find( '.cdx-label__label__text' ).text() === name );

	if ( field === undefined ) {
		throw new Error( `No field named ${ name }` );
	}

	return field.find<HTMLInputElement>( 'input' );
}

// By what it says: "Add Birth", or "Add" where it opens a menu of the target Schemas.
function addButtons( wrapper: VueWrapper ): DOMWrapper<HTMLButtonElement>[] {
	return wrapper.findAll<HTMLButtonElement>( 'button' )
		.filter( ( button ) => button.text().startsWith( 'neowiki-dependent-subject-add' ) );
}

function addButton( wrapper: VueWrapper ): DOMWrapper<HTMLButtonElement> {
	const [ button ] = addButtons( wrapper );

	if ( button === undefined ) {
		throw new Error( 'No add button' );
	}

	return button;
}

function removeLabels( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( '.ext-neowiki-dependent-subject-editor__remove' )
		.map( ( button ) => accessibleName( button.element ) );
}

function lastValue( wrapper: VueWrapper ): RelationValue | undefined {
	const emitted = input( wrapper ).emitted( 'update:modelValue' ) ?? [];

	return emitted[ emitted.length - 1 ]?.[ 0 ] as RelationValue | undefined;
}

function lastValueTargets( wrapper: VueWrapper ): string[] {
	return lastValue( wrapper )?.relations.map( ( relation ) => relation.target.text ) ?? [];
}

async function addByButton( wrapper: VueWrapper ): Promise<void> {
	await addButton( wrapper ).trigger( 'click' );
	await flushPromises();
}

async function addFromMenu( wrapper: VueWrapper, schemaName: string ): Promise<void> {
	await addButton( wrapper ).trigger( 'click' );
	await wrapper.findAll( '.cdx-menu-item' )
		.find( ( item ) => item.text() === schemaName )
		?.trigger( 'click' );
	await flushPromises();
}

describe( 'DependentSubjectsInput', () => {

	beforeEach( () => {
		setupMwMock( {
			functions: [ 'message', 'msg', 'notify', 'config', 'util' ],
			// Blur-only mode: the dry-run fires on blur and on mount, never on a keystroke.
			config: { wgNeoWikiValidationDebounceMs: 0 },
		} );
		setActivePinia( createPinia() );

		validateSubject = vi.fn().mockResolvedValue( [] );
		validateSubjectUpdate = vi.fn().mockResolvedValue( [] );
		useSubjectStore().validateSubject = validateSubject;
		useSubjectStore().validateSubjectUpdate = validateSubjectUpdate;
	} );

	it( 'shows the fields of each Dependent Subject the value points at, as they are stored', async () => {
		const wrapper = mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ] } );
		await flushPromises();

		expect( fieldNamed( wrapper, 'Date' ).element.value ).toBe( '25 October 1881' );
		expect( fieldNamed( wrapper, 'Archive' ).element.value ).toBe( 'Archivo Municipal' );
	} );

	it( 'offers to add a Dependent Subject of its one target Schema, by that Schema\'s name', async () => {
		const wrapper = mountInput();
		await flushPromises();

		expect( addButton( wrapper ).text() ).toBe( 'neowiki-dependent-subject-addBirth' );
	} );

	it( 'adds a Dependent Subject, pointing the value at it', async () => {
		const wrapper = mountInput();
		await flushPromises();

		await addByButton( wrapper );

		expect( lastValueTargets( wrapper ) ).toEqual( [ MINTED_ID ] );
		expect( fieldNamed( wrapper, 'Date' ).element.value ).toBe( '' );
	} );

	it( 'keeps its add control where the focus can stay while it adds, marked as unavailable', async () => {
		const mint = mintingOnRelease();
		const wrapper = mountInput( { mintSubjectId: mint.mintSubjectId } );
		await flushPromises();

		await addButton( wrapper ).trigger( 'click' );

		expect( addButton( wrapper ).element.disabled ).toBe( false );
		expect( addButton( wrapper ).attributes( 'aria-disabled' ) ).toBe( 'true' );
		mint.release();
	} );

	it( 'keeps its menu of target Schemas where the focus can stay while it adds, marked as unavailable', async () => {
		const mint = mintingOnRelease();
		const wrapper = mountInput( { property: namesProperty, mintSubjectId: mint.mintSubjectId } );
		await flushPromises();

		await addFromMenu( wrapper, 'Pseudonym' );

		expect( addButton( wrapper ).element.disabled ).toBe( false );
		expect( addButton( wrapper ).attributes( 'aria-disabled' ) ).toBe( 'true' );
		mint.release();
	} );

	it( 'adds one Dependent Subject however often its add control is pressed while it adds', async () => {
		const mint = mintingOnRelease();
		const wrapper = mountInput( {
			property: newRelationProperty( { name: 'Births', targetSchemas: [ 'Birth' ], multiple: true } ),
			mintSubjectId: mint.mintSubjectId,
		} );
		await flushPromises();

		await addButton( wrapper ).trigger( 'click' );
		await addButton( wrapper ).trigger( 'click' );
		mint.release();
		await flushPromises();

		expect( lastValueTargets( wrapper ) ).toEqual( [ MINTED_ID ] );
	} );

	it( 'moves the focus to the first field of the Dependent Subject it adds', async () => {
		const wrapper = mountInput();
		await flushPromises();

		await addByButton( wrapper );

		expect( document.activeElement ).toBe( fieldNamed( wrapper, 'Date' ).element );
	} );

	it( 'asks which target Schema to add one of where there are several', async () => {
		const wrapper = mountInput( { property: namesProperty } );
		await flushPromises();

		await addFromMenu( wrapper, 'Pseudonym' );

		expect( lastValueTargets( wrapper ) ).toEqual( [ MINTED_ID ] );
		expect( groups( wrapper )[ 0 ].find( '.ext-neowiki-schema-name__text' ).text() ).toBe( 'Pseudonym' );
	} );

	it( 'says which Schema each Dependent Subject has only where the property has several', async () => {
		const wrapper = mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ] } );
		await flushPromises();

		expect( groups( wrapper )[ 0 ].find( '.ext-neowiki-dependent-subject-editor__schema' ).exists() ).toBe( false );
	} );

	it( 'offers additions only of the target Schemas the editor could load', async () => {
		const wrapper = mountInput( { property: namesProperty, schemas: SCHEMAS.filter( ( schema ) => schema !== pseudonymSchema ) } );
		await flushPromises();

		expect( addButton( wrapper ).text() ).toBe( 'neowiki-dependent-subject-addName' );
	} );

	it( 'offers no addition once a single-valued property holds one', async () => {
		const wrapper = mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ] } );
		await flushPromises();

		expect( addButtons( wrapper ) ).toEqual( [] );
	} );

	it( 'goes on offering additions to a property of several values', async () => {
		const wrapper = mountInput( { property: namesProperty, value: pointingAt( NAME_ID ), stored: [ storedName( NAME_ID, 'Pablo' ) ] } );
		await flushPromises();

		expect( addButtons( wrapper ) ).toHaveLength( 1 );
	} );

	it( 'removes a Dependent Subject, pointing the value away from it', async () => {
		const wrapper = mountInput( {
			property: namesProperty,
			value: pointingAt( NAME_ID, SECOND_NAME_ID ),
			stored: [ storedName( NAME_ID, 'Pablo' ), storedName( SECOND_NAME_ID, 'Ruiz' ) ],
		} );
		await flushPromises();

		await wrapper.find( '.ext-neowiki-dependent-subject-editor__remove' ).trigger( 'click' );
		await flushPromises();

		expect( lastValueTargets( wrapper ) ).toEqual( [ SECOND_NAME_ID ] );
		expect( fieldNamed( wrapper, 'Text' ).element.value ).toBe( 'Ruiz' );
	} );

	it( 'names each remove control after its Dependent Subject\'s Schema, numbered where there are several', async () => {
		const wrapper = mountInput( {
			property: namesProperty,
			value: pointingAt( NAME_ID, SECOND_NAME_ID ),
			stored: [ storedName( NAME_ID, 'Pablo' ), storedName( SECOND_NAME_ID, 'Ruiz' ) ],
		} );
		await flushPromises();

		expect( removeLabels( wrapper ) ).toEqual( [
			'neowiki-dependent-subject-removeneowiki-dependent-subject-numberedName1',
			'neowiki-dependent-subject-removeneowiki-dependent-subject-numberedName2',
		] );
	} );

	it( 'names a lone remove control after its Dependent Subject\'s Schema alone', async () => {
		const wrapper = mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ] } );
		await flushPromises();

		expect( removeLabels( wrapper )[ 0 ] ).toBe( 'neowiki-dependent-subject-removeBirth' );
	} );

	it( 'numbers the Dependent Subjects of each Schema apart, and only those of a Schema it holds several of', async () => {
		const PSEUDONYM_ID = 's1pseudonymAAA1';
		const wrapper = mountInput( {
			property: namesProperty,
			value: pointingAt( NAME_ID, PSEUDONYM_ID, SECOND_NAME_ID ),
			stored: [ storedName( NAME_ID, 'Pablo' ), storedName( PSEUDONYM_ID, 'P. Ruiz', 'Pseudonym' ), storedName( SECOND_NAME_ID, 'Ruiz' ) ],
		} );
		await flushPromises();

		expect( removeLabels( wrapper ) ).toEqual( [
			'neowiki-dependent-subject-removeneowiki-dependent-subject-numberedName1',
			'neowiki-dependent-subject-removePseudonym',
			'neowiki-dependent-subject-removeneowiki-dependent-subject-numberedName2',
		] );
	} );

	it( 'says what its remove control removes on hover too', async () => {
		const wrapper = mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ] } );
		await flushPromises();

		expect( wrapper.find( '.ext-neowiki-dependent-subject-editor__remove' ).attributes( 'title' ) )
			.toBe( 'neowiki-dependent-subject-removeBirth' );
	} );

	it( 'reports the Dependent Subjects as their fields hold them, each followed by those inside it', async () => {
		const wrapper = mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ] } );
		await flushPromises();

		await fieldNamed( wrapper, 'Date' ).setValue( '25 Oct 1881' );

		const [ birth, record ] = exposed( wrapper ).dependentSubjects();
		expect( birth.getStatementValue( new PropertyName( 'Date' ) ) ).toEqual( newStringValue( '25 Oct 1881' ) );
		expect( record.getId().text ).toBe( RECORD_ID );
	} );

	it( 'keeps the Statements a Dependent Subject\'s Schema does not declare', async () => {
		const midwife = new Statement( new PropertyName( 'Midwife' ), TextType.typeName, newStringValue( 'Inés' ) );
		const birthWithMidwife = storedBirth.withStatements( new StatementList( [ ...storedBirth.getStatements(), midwife ] ) );
		const wrapper = mountInput( { value: pointingAt( BIRTH_ID ), stored: [ birthWithMidwife, storedRecord ] } );
		await flushPromises();

		await fieldNamed( wrapper, 'Date' ).setValue( '25 Oct 1881' );

		const [ birth ] = exposed( wrapper ).dependentSubjects();
		expect( birth.getStatements().get( new PropertyName( 'Midwife' ) ) ).toEqual( midwife );
	} );

	it( 'shows an error in place of a Dependent Subject that did not load, and reports none for it', async () => {
		const wrapper = mountInput( { value: pointingAt( BIRTH_ID ) } );
		await flushPromises();

		expect( groups( wrapper )[ 0 ].text() ).toContain( 'neowiki-dependent-subject-load-error' );
		expect( exposed( wrapper ).dependentSubjects() ).toEqual( [] );
	} );

	it( 'reports text in a Dependent Subject\'s field that the field cannot read', async () => {
		const wrapper = mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ] } );
		await flushPromises();

		await reportUnparseableNumber( wrapper.find( 'input[type="number"]' ) );

		expect( exposed( wrapper ).unparseableInputMessage() ).toBe( 'neowiki-field-invalid-number' );
	} );

	it( 'clears the violations of the property once a Dependent Subject is added', async () => {
		const wrapper = mountInput( {
			serverViolations: [ { propertyName: 'Birth', code: 'required', args: [], severity: 'error', valuePartIndex: null } ],
		} );
		await flushPromises();

		await addByButton( wrapper );

		expect( input( wrapper ).emitted( 'clear-server-violation' ) ).toContainEqual( [ { propertyName: 'Birth', valuePartIndex: null } ] );
	} );

	it( 'clears the violations of the property once a Dependent Subject is removed', async () => {
		const wrapper = mountInput( {
			value: pointingAt( BIRTH_ID ),
			stored: [ storedBirth, storedRecord ],
			serverViolations: [ { propertyName: 'Birth', code: 'some-code', args: [], severity: 'error', valuePartIndex: null } ],
		} );
		await flushPromises();

		await wrapper.find( '.ext-neowiki-dependent-subject-editor__remove' ).trigger( 'click' );
		await flushPromises();

		expect( input( wrapper ).emitted( 'clear-server-violation' ) ).toContainEqual( [ { propertyName: 'Birth', valuePartIndex: null } ] );
	} );

	it( 'shows a violation of the property itself as its field\'s message', async () => {
		const wrapper = mountInput( {
			serverViolations: [ { propertyName: 'Birth', code: 'required', args: [], severity: 'warning', valuePartIndex: null } ],
		} );
		await flushPromises();

		expect( wrapper.find( '.cdx-field__validation-message' ).text() ).toBe( 'neowiki-field-required' );
	} );

	it( 'tells of a change to a field inside a Dependent Subject as no change to a relation', async () => {
		const wrapper = mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ] } );
		await flushPromises();

		await fieldNamed( wrapper, 'Date' ).setValue( '1881' );

		expect( input( wrapper ).emitted( 'dependent-subject-change' ) ).toContainEqual( [ false ] );
		expect( input( wrapper ).emitted( 'dependent-subject-change' ) ).not.toContainEqual( [ true ] );
	} );

	it( 'tells of a change to a relation inside a Dependent Subject as one', async () => {
		const wrapper = mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ] } );
		await flushPromises();

		await wrapper.findAll( '.ext-neowiki-dependent-subject-editor__remove' )[ 1 ].trigger( 'click' );
		await flushPromises();

		expect( input( wrapper ).emitted( 'dependent-subject-change' ) ).toContainEqual( [ true ] );
	} );

	describe( 'validation', () => {

		it( 'checks a Dependent Subject the wiki holds as an update as soon as it shows', async () => {
			mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ] } );
			await flushPromises();

			expect( validateSubjectUpdate ).toHaveBeenCalledWith( new SubjectId( BIRTH_ID ), null, expect.any( StatementList ) );
		} );

		it( 'checks an added one as a creation, without complaining of fields not filled in yet', async () => {
			validateSubject.mockResolvedValue( [
				{ propertyName: 'Date', code: 'required', args: [], severity: 'error', valuePartIndex: null },
			] );
			const wrapper = mountInput();
			await flushPromises();
			await addByButton( wrapper );

			await fieldNamed( wrapper, 'Date' ).setValue( 'Spring' );
			await fieldNamed( wrapper, 'Date' ).trigger( 'focusout' );
			await flushPromises();

			expect( validateSubject ).toHaveBeenCalledWith( null, 'Birth', expect.any( StatementList ) );
			expect( wrapper.find( '.cdx-field__validation-message' ).exists() ).toBe( false );
		} );

		it( 'withholds the complaint about a relation to a Subject the wiki does not hold yet', async () => {
			validateSubjectUpdate.mockResolvedValue( [ {
				propertyName: 'Record',
				code: 'relation-target-not-found',
				args: [ RECORD_ID ],
				severity: 'warning',
				valuePartIndex: null,
			} ] );

			const wrapper = mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ], draftIds: [ RECORD_ID ] } );
			await flushPromises();

			expect( wrapper.text() ).not.toContain( 'neowiki-field-relation-target-not-found' );
		} );

		it( 'shows what a refused save reported on a Dependent Subject at its field', async () => {
			const savedViolations = ref<readonly SubjectViolation[]>( [] );
			const wrapper = mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ], savedViolations } );
			await flushPromises();

			savedViolations.value = [
				{ propertyName: 'Date', code: 'max-length', args: [ '4' ], severity: 'error', valuePartIndex: null, subjectId: BIRTH_ID },
				{ propertyName: 'Archive', code: 'required', args: [], severity: 'error', valuePartIndex: null, subjectId: RECORD_ID },
			];
			await flushPromises();

			const [ birth, record ] = groups( wrapper );
			expect( birth.find( '.cdx-field__validation-message' ).text() ).toBe( 'neowiki-field-max-length4' );
			expect( record.find( '.cdx-field__validation-message' ).text() ).toBe( 'neowiki-field-required' );
		} );

		it( 'shows what a refused save reported on one Dependent Subject at that one alone', async () => {
			const savedViolations = ref<readonly SubjectViolation[]>( [] );
			const wrapper = mountInput( {
				property: namesProperty,
				value: pointingAt( NAME_ID, SECOND_NAME_ID ),
				stored: [ storedName( NAME_ID, 'Pablo' ), storedName( SECOND_NAME_ID, 'Ruiz' ) ],
				savedViolations,
			} );
			await flushPromises();

			savedViolations.value = [
				{ propertyName: 'Text', code: 'max-length', args: [ '3' ], severity: 'error', valuePartIndex: null, subjectId: SECOND_NAME_ID },
			];
			await flushPromises();

			const [ first, second ] = groups( wrapper );
			expect( first.find( '.cdx-field__validation-message' ).exists() ).toBe( false );
			expect( second.find( '.cdx-field__validation-message' ).text() ).toBe( 'neowiki-field-max-length3' );
		} );

	} );

	describe( 'undoing a removal', () => {

		const THIRD_NAME_ID = 's1nameAAAAAAAA3';

		function mountNames( value: RelationValue = pointingAt( NAME_ID, SECOND_NAME_ID, THIRD_NAME_ID ) ): VueWrapper {
			return mountInput( {
				property: namesProperty,
				value,
				stored: [ storedName( NAME_ID, 'Pablo' ), storedName( SECOND_NAME_ID, 'Ruiz' ), storedName( THIRD_NAME_ID, 'Picasso' ) ],
			} );
		}

		function mountBirth(): VueWrapper {
			return mountInput( { value: pointingAt( BIRTH_ID ), stored: [ storedBirth, storedRecord ] } );
		}

		// In document order, so a Dependent Subject inside another comes after the remove control of the one around it.
		async function removeGroup( wrapper: VueWrapper, index: number ): Promise<void> {
			await wrapper.findAll( '.ext-neowiki-dependent-subject-editor__remove' )[ index ].trigger( 'click' );
			await flushPromises();
		}

		function undoButton( wrapper: VueWrapper ): DOMWrapper<HTMLButtonElement> {
			const button = wrapper.findAll<HTMLButtonElement>( 'button' )
				.find( ( candidate ) => candidate.text() === 'neowiki-dependent-subject-undo' );

			if ( button === undefined ) {
				throw new Error( 'No Undo button' );
			}

			return button;
		}

		async function undo( wrapper: VueWrapper ): Promise<void> {
			await undoButton( wrapper ).trigger( 'click' );
			await flushPromises();
		}

		function removalCards( wrapper: VueWrapper ): DOMWrapper<Element>[] {
			return wrapper.findAll( '.ext-neowiki-dependent-subjects-input__removed' );
		}

		// Each Dependent Subject by the text in its first field, each card by what it says, in the order shown.
		function shown( wrapper: VueWrapper ): string[] {
			return wrapper.findAll( '.ext-neowiki-dependent-subject-editor, .ext-neowiki-dependent-subjects-input__removed' )
				.filter( ( item ) => item.isVisible() )
				.map( ( item ) => item.classes( 'ext-neowiki-dependent-subject-editor' ) ?
					item.find<HTMLInputElement>( 'input' ).element.value :
					item.text() );
		}

		it( 'shows a card saying what was removed in the place of the removed Dependent Subject', async () => {
			const wrapper = mountNames();
			await flushPromises();

			await removeGroup( wrapper, 1 );

			expect( shown( wrapper ) ).toEqual( [
				'Pablo',
				expect.stringContaining( 'neowiki-dependent-subject-removedneowiki-dependent-subject-numberedName2' ),
				'Picasso',
			] );
		} );

		it( 'moves the focus to the card\'s Undo button', async () => {
			const wrapper = mountNames();
			await flushPromises();

			await removeGroup( wrapper, 1 );

			expect( document.activeElement ).toBe( undoButton( wrapper ).element );
		} );

		it( 'says what was removed where the focus goes, once', async () => {
			const wrapper = mountNames();
			await flushPromises();

			await removeGroup( wrapper, 1 );

			expect( describedAs( wrapper, undoButton( wrapper ) ) )
				.toEqual( [ 'neowiki-dependent-subject-removedneowiki-dependent-subject-numberedName2' ] );
			expect( wrapper.find( '[aria-live], [role="status"]' ).exists() ).toBe( false );
		} );

		it( 'brings the Dependent Subject back in its place, with what was typed into it', async () => {
			const wrapper = mountNames();
			await flushPromises();
			await fieldNamed( wrapper, 'Text' ).setValue( 'Pablo Diego' );

			await removeGroup( wrapper, 0 );
			await undo( wrapper );

			expect( shown( wrapper ) ).toEqual( [ 'Pablo Diego', 'Ruiz', 'Picasso' ] );
		} );

		it( 'brings back the Dependent Subjects inside it, with what was typed into them', async () => {
			const wrapper = mountBirth();
			await flushPromises();
			await fieldNamed( wrapper, 'Archive' ).setValue( 'Archivo Histórico' );

			await removeGroup( wrapper, 0 );
			await undo( wrapper );

			expect( fieldNamed( wrapper, 'Archive' ).element.value ).toBe( 'Archivo Histórico' );
			expect( exposed( wrapper ).dependentSubjects().map( ( subject ) => subject.getId().text ) ).toEqual( [ BIRTH_ID, RECORD_ID ] );
		} );

		it( 'points the value at it again with the Relation it had', async () => {
			const stored = new RelationValue( [
				newRelation( 'r1nameAAAAAAAA1', NAME_ID ),
				newRelation( 'r1nameAAAAAAAA2', SECOND_NAME_ID ),
				newRelation( 'r1nameAAAAAAAA3', THIRD_NAME_ID ),
			] );
			const wrapper = mountNames( stored );
			await flushPromises();

			await removeGroup( wrapper, 1 );
			await undo( wrapper );

			expect( lastValue( wrapper ) ).toEqual( stored );
		} );

		it( 'moves the focus to the first field of the Dependent Subject it brings back', async () => {
			const wrapper = mountNames();
			await flushPromises();

			await removeGroup( wrapper, 1 );
			await undo( wrapper );

			expect( document.activeElement ).toBe( groups( wrapper )[ 1 ].find( 'input' ).element );
		} );

		it( 'leaves a removed Dependent Subject and those inside it out of what the save writes', async () => {
			const wrapper = mountBirth();
			await flushPromises();

			await removeGroup( wrapper, 0 );

			expect( exposed( wrapper ).dependentSubjects() ).toEqual( [] );
		} );

		it( 'does not hold the save for text a removed Dependent Subject\'s field cannot read', async () => {
			const wrapper = mountBirth();
			await flushPromises();
			await reportUnparseableNumber( wrapper.find( 'input[type="number"]' ) );

			await removeGroup( wrapper, 0 );

			expect( exposed( wrapper ).unparseableInputMessage() ).toBeNull();
		} );

		it( 'offers to add one again beside the card of a single-valued property', async () => {
			const wrapper = mountBirth();
			await flushPromises();

			await removeGroup( wrapper, 0 );

			expect( addButton( wrapper ).text() ).toBe( 'neowiki-dependent-subject-addBirth' );
		} );

		it( 'drops the card of a single-valued property once a Dependent Subject is added in its place', async () => {
			const wrapper = mountBirth();
			await flushPromises();
			await removeGroup( wrapper, 0 );

			await addByButton( wrapper );

			expect( removalCards( wrapper ) ).toHaveLength( 0 );
			expect( lastValueTargets( wrapper ) ).toEqual( [ MINTED_ID ] );
		} );

		it( 'keeps the cards of a property of several values when one is added', async () => {
			const wrapper = mountNames();
			await flushPromises();
			await removeGroup( wrapper, 1 );

			await addFromMenu( wrapper, 'Name' );

			expect( removalCards( wrapper ) ).toHaveLength( 1 );
		} );

		it( 'removes a Dependent Subject inside one it brought back', async () => {
			const wrapper = mountBirth();
			await flushPromises();
			await removeGroup( wrapper, 0 );
			await undo( wrapper );

			await removeGroup( wrapper, 1 );

			expect( exposed( wrapper ).dependentSubjects().map( ( subject ) => subject.getId().text ) ).toEqual( [ BIRTH_ID ] );
		} );

	} );

} );
