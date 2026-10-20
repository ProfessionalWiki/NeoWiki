import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import type { SubjectEditorExposes } from '@/components/SubjectEditor/SubjectEditor.vue';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition.ts';
import RelationInput from '@/components/Value/RelationInput.vue';
import TextInput from '@/components/Value/TextInput.vue';
import { newStringValue, RelationValue } from '@/domain/Value.ts';
import { NumberType } from '@/domain/propertyTypes/Number.ts';
import { newRelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { newSelectProperty } from '@/domain/propertyTypes/Select.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { newSchema } from '@/TestHelpers.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { NeoWikiTestServices } from '../../NeoWikiTestServices.ts';
import {
	createI18nMock,
	createTestWrapper,
	dialogOfAnotherExtension,
	guardMenuEscapes,
	listenForDialogKeys,
	openMenuAndPressEscape,
	reportUnparseableNumber,
} from '../../VueTestHelpers.ts';
import DependentSubjectsInput from '@/components/SubjectEditor/DependentSubjectsInput.vue';
import { DependentSubjectEditingKey, type DependentSubjectEditing } from '@/components/SubjectEditor/DependentSubjectEditing.ts';
import { createPinia, setActivePinia } from 'pinia';

describe( 'SubjectEditor', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( key: string ) => ( {
				text: () => key,
				parse: () => key,
			} ) ),
		} );
	} );

	const schema = newSchema( {
		title: 'TestSchema',
		description: 'A test schema',
		properties: new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'Name', { type: TextType.typeName } ),
			createPropertyDefinitionFromJson( 'Score', { type: NumberType.typeName } ),
			// Sits after the number field, so an editor that only consulted the last
			// field would report nothing unparseable.
			createPropertyDefinitionFromJson( 'Notes', { type: TextType.typeName } ),
		] ),
	} );

	function newWrapper(): VueWrapper {
		return createTestWrapper( SubjectEditor, {
			statements: schema.blankStatements(),
			schema,
		} );
	}

	function editor( wrapper: VueWrapper ): SubjectEditorExposes {
		return wrapper.vm as unknown as SubjectEditorExposes;
	}

	it( 'reports no unparseable input while every field can be read', () => {
		const wrapper = newWrapper();

		expect( editor( wrapper ).saveBlocker() ).toBeNull();
	} );

	it( 'reports unparseable input when one of its fields holds text it cannot turn into a value', async () => {
		const wrapper = newWrapper();

		await reportUnparseableNumber( wrapper.find( 'input[type="number"]' ) );

		expect( editor( wrapper ).saveBlocker() )
			.toEqual( { propertyName: 'Score', message: 'neowiki-field-invalid-number' } );
	} );

	// A dry-run response re-renders the editor; the report lives in the field, so it
	// survives that — but would be lost if fields were remounted rather than patched.
	it( 'keeps reporting unparseable input after a re-render', async () => {
		const wrapper = newWrapper();
		await reportUnparseableNumber( wrapper.find( 'input[type="number"]' ) );

		await wrapper.setProps( {
			serverViolations: [
				{ propertyName: 'Name', code: 'required', args: [], severity: 'error', valuePartIndex: null },
			],
		} );

		expect( editor( wrapper ).saveBlocker() )
			.toEqual( { propertyName: 'Score', message: 'neowiki-field-invalid-number' } );
	} );

	// The schema can change while the dialog is open (the nested schema editor); a
	// stale reference to a field no longer in the schema must not keep holding saves.
	it( 'stops reporting unparseable input once the field is gone from the schema', async () => {
		const withTrailingNumber = newSchema( {
			title: 'TestSchema',
			description: 'A test schema',
			properties: new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Name', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'Score', { type: NumberType.typeName } ),
			] ),
		} );
		const wrapper = createTestWrapper( SubjectEditor, {
			statements: withTrailingNumber.blankStatements(),
			schema: withTrailingNumber,
		} );
		await reportUnparseableNumber( wrapper.find( 'input[type="number"]' ) );

		const withoutNumber = newSchema( {
			title: 'TestSchema',
			description: 'A test schema',
			properties: new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Name', { type: TextType.typeName } ),
			] ),
		} );
		await wrapper.setProps( { schema: withoutNumber, statements: withoutNumber.blankStatements() } );

		expect( editor( wrapper ).saveBlocker() ).toBeNull();
	} );

	describe( 'in a dialog of another extension', () => {
		const statusSchema = newSchema( {
			properties: new PropertyDefinitionList( [
				newSelectProperty( { name: 'Status', options: [ { id: 'o1', label: 'Active' }, { id: 'o2', label: 'Inactive' } ] } ),
			] ),
		} );

		it( 'keeps the Escape that closes the menu of one of its fields from that dialog', async () => {
			guardMenuEscapes();
			const dialogKeyup = listenForDialogKeys();
			const wrapper = createTestWrapper( SubjectEditor, { statements: statusSchema.blankStatements(), schema: statusSchema } );
			dialogOfAnotherExtension().append( wrapper.element );
			const trigger = wrapper.find<HTMLElement>( '[role="combobox"]' );

			await openMenuAndPressEscape( trigger.element );

			expect( trigger.attributes( 'aria-expanded' ) ).toBe( 'false' );
			expect( dialogKeyup ).not.toHaveBeenCalled();
		} );
	} );

	// A relation edit is the one that changes the Subject graph, so it is the one the tree
	// listens for; a text edit must not wake it.
	describe( 'relation-change', () => {
		const mixedSchema = newSchema( {
			properties: new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Name', { type: TextType.typeName } ),
				newRelationProperty( { name: 'Author' } ),
			] ),
		} );

		function mountMixed(): VueWrapper {
			return mount( SubjectEditor, {
				props: {
					statements: mixedSchema.blankStatements(),
					schema: mixedSchema,
				},
				global: {
					provide: NeoWikiTestServices.getServices(),
					directives: { tooltip: {} },
					mocks: { $i18n: createI18nMock() },
					stubs: { SubjectPicker: true, NeoMultiLookupInput: true },
				},
			} );
		}

		it( 'is emitted when a relation field reports a new value', async () => {
			const wrapper = mountMixed();

			await wrapper.findComponent( RelationInput ).vm.$emit( 'update:modelValue', new RelationValue( [] ) );

			expect( wrapper.emitted( 'relation-change' ) ).toHaveLength( 1 );
		} );

		it( 'is not emitted when a text field reports a new value', async () => {
			const wrapper = mountMixed();

			await wrapper.findComponent( TextInput ).vm.$emit( 'update:modelValue', newStringValue( 'x' ) );

			expect( wrapper.emitted( 'change' ) ).toHaveLength( 1 );
			expect( wrapper.emitted( 'relation-change' ) ).toBeUndefined();
		} );
	} );

	describe( 'Dependent Subjects', () => {
		const hostSchema = newSchema( {
			properties: new PropertyDefinitionList( [
				newRelationProperty( { name: 'Birth', targetSchemas: [ 'Birth' ] } ),
				newRelationProperty( { name: 'Employer', targetSchemas: [ 'Company' ] } ),
			] ),
		} );

		const editing: DependentSubjectEditing = {
			targetsDependentSubjects: ( property ) => property.targetSchemas.includes( 'Birth' ),
			schema: () => undefined,
			subject: () => undefined,
			isNew: () => false,
			markChanged: () => undefined,
			add: () => Promise.resolve( null ),
			unsavedTargetIds: () => [],
		};

		function mountHost( provide: Record<symbol, unknown> ): VueWrapper {
			setActivePinia( createPinia() );

			return mount( SubjectEditor, {
				props: {
					statements: hostSchema.blankStatements(),
					schema: hostSchema,
				},
				global: {
					provide: { ...NeoWikiTestServices.getServices(), ...provide },
					directives: { tooltip: {} },
					mocks: { $i18n: createI18nMock() },
					stubs: { SubjectPicker: true, NeoMultiLookupInput: true },
				},
			} );
		}

		function fieldComponents( wrapper: VueWrapper ): Record<string, string> {
			return Object.fromEntries( [
				...wrapper.findAllComponents( DependentSubjectsInput ).map( ( field ) => [ field.props( 'label' ), 'inline' ] ),
				...wrapper.findAllComponents( RelationInput ).map( ( field ) => [ field.props( 'label' ), 'picker' ] ),
			] );
		}

		it( 'edits a relation\'s Dependent Subjects inline, and picks the targets of the other relations', () => {
			const wrapper = mountHost( { [ DependentSubjectEditingKey as symbol ]: editing } );

			expect( fieldComponents( wrapper ) ).toEqual( { Birth: 'inline', Employer: 'picker' } );
		} );

		it( 'picks the targets of every relation where the editor does not edit Dependent Subjects', () => {
			const wrapper = mountHost( {} );

			expect( fieldComponents( wrapper ) ).toEqual( { Birth: 'picker', Employer: 'picker' } );
		} );

		it( 'reports no Dependent Subjects where no field edits them', () => {
			const wrapper = mountHost( {} );

			expect( editor( wrapper ).getDependentSubjects() ).toEqual( [] );
		} );

		it( 'passes on a change inside a Dependent Subject, saying whether it is to a relation', async () => {
			const wrapper = mountHost( { [ DependentSubjectEditingKey as symbol ]: editing } );

			await wrapper.findComponent( DependentSubjectsInput ).vm.$emit( 'dependent-subject-change', true );

			expect( wrapper.emitted( 'dependent-subject-change' ) ).toEqual( [ [ true ] ] );
		} );

		it( 'tells of no change of its own for a change inside a Dependent Subject', async () => {
			const wrapper = mountHost( { [ DependentSubjectEditingKey as symbol ]: editing } );

			await wrapper.findComponent( DependentSubjectsInput ).vm.$emit( 'dependent-subject-change', false );

			expect( wrapper.emitted( 'change' ) ).toBeUndefined();
		} );
	} );

	// Mounted here rather than through createTestWrapper: the relation field reaches
	// SubjectPicker, which calls useSubjectStore() at setup, and createTestWrapper
	// installs neither Pinia nor stubs.
	it( 'forwards edit-relation-target from a value input', () => {
		const relationSchema = newSchema( {
			properties: new PropertyDefinitionList( [ newRelationProperty( { name: 'Author' } ) ] ),
		} );
		const wrapper = mount( SubjectEditor, {
			props: {
				statements: relationSchema.blankStatements(),
				schema: relationSchema,
			},
			global: {
				provide: NeoWikiTestServices.getServices(),
				directives: { tooltip: {} },
				mocks: { $i18n: createI18nMock() },
				stubs: { SubjectPicker: true, NeoMultiLookupInput: true },
			},
		} );

		const id = new SubjectId( 's11111111111111' );
		wrapper.findComponent( RelationInput ).vm.$emit( 'edit-relation-target', id );

		expect( wrapper.emitted( 'edit-relation-target' ) ).toEqual( [ [ id ] ] );
	} );
} );
