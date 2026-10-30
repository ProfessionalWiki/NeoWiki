import './../neowiki-test-env.ts';
import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import type { Pinia } from 'pinia';
import { reactive } from 'vue';
import { CdxDialog, CdxTextInput } from '@wikimedia/codex';
import EditMainSubjectDialog from '@redherb/editMainSubject/EditMainSubjectDialog.vue';
import editMainSubjectConstants from '@redherb/editMainSubject/constants.js';
import { createPropertyDefinitionFromJson, PropertyName } from '@/domain/PropertyDefinition.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { Schema } from '@/domain/Schema.ts';
import { Statement } from '@/domain/Statement.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { newStringValue } from '@/domain/Value.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import { NeoWikiTestServices } from '../NeoWikiTestServices.ts';
import { createI18nMock, setupMwMock } from '../VueTestHelpers.ts';
import { loadRedHerbFrontend } from './RedHerbRegistration.ts';

const DIALOG_STATE_KEY = editMainSubjectConstants.DIALOG_STATE_KEY;

const SCHEMA_NAME = 'Company';
const SUBJECT_ID = 's1demo5sssssss1';

const companySchema = new Schema(
	SCHEMA_NAME,
	'A company',
	new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'tradingName', { type: TextType.typeName } ),
	] ),
);

const company = new Subject(
	new SubjectId( SUBJECT_ID ),
	'Acme',
	SCHEMA_NAME,
	new StatementList( [
		new Statement( new PropertyName( 'tradingName' ), TextType.typeName, newStringValue( 'Acme Corporation' ) ),
	] ),
);

function pendingForever<T>(): Promise<T> {
	return new Promise( () => {
		// Never settles, so the caller stays in its loading state.
	} );
}

interface DialogState {
	open: boolean;
	subjectId: string | null;
}

describe( 'EditMainSubjectDialog', () => {
	let pinia: Pinia;
	let state: DialogState;

	beforeAll( async () => {
		await loadRedHerbFrontend();
	} );

	beforeEach( () => {
		setupMwMock();
		( globalThis as any ).mw.log = { error: vi.fn() };

		pinia = createPinia();
		setActivePinia( pinia );

		useSubjectStore().getOrFetchSubject = vi.fn().mockResolvedValue( company );
		useSubjectStore().updateSubject = vi.fn().mockResolvedValue( undefined );
		useSchemaStore().getOrFetchSchema = vi.fn().mockResolvedValue( companySchema );

		state = reactive( { open: false, subjectId: null } );
	} );

	function newWrapper(): VueWrapper {
		return mount( EditMainSubjectDialog, {
			global: {
				plugins: [ pinia ],
				provide: {
					...NeoWikiTestServices.getServices(),
					[ DIALOG_STATE_KEY ]: state,
				},
				directives: { tooltip: {} },
				mocks: { $i18n: createI18nMock() },
				stubs: { teleport: true },
			},
		} );
	}

	async function openForSubject(): Promise<VueWrapper> {
		const wrapper = newWrapper();
		state.subjectId = SUBJECT_ID;
		state.open = true;
		await flushPromises();
		return wrapper;
	}

	function textInputAt( wrapper: VueWrapper, index: number ): VueWrapper {
		return wrapper.findAllComponents( CdxTextInput )[ index ] as unknown as VueWrapper;
	}

	function labelInput( wrapper: VueWrapper ): VueWrapper {
		return textInputAt( wrapper, 0 );
	}

	function labelValue( wrapper: VueWrapper ): string {
		return ( wrapper.findAll( 'input' )[ 0 ].element as HTMLInputElement ).value;
	}

	async function typeLabel( wrapper: VueWrapper, label: string ): Promise<void> {
		await labelInput( wrapper ).vm.$emit( 'update:modelValue', label );
	}

	async function save( wrapper: VueWrapper ): Promise<void> {
		await wrapper.findComponent( CdxDialog ).vm.$emit( 'primary' );
	}

	function savedSubject(): Subject {
		return vi.mocked( useSubjectStore().updateSubject ).mock.calls[ 0 ][ 0 ];
	}

	it( 'loads the subject it was opened for', async () => {
		await openForSubject();

		expect( useSubjectStore().getOrFetchSubject ).toHaveBeenCalledWith( new SubjectId( SUBJECT_ID ) );
	} );

	it( 'loads the schema of the subject', async () => {
		await openForSubject();

		expect( useSchemaStore().getOrFetchSchema ).toHaveBeenCalledWith( SCHEMA_NAME );
	} );

	it( 'loads nothing while no subject is set', async () => {
		newWrapper();
		state.open = true;
		await flushPromises();

		expect( useSubjectStore().getOrFetchSubject ).not.toHaveBeenCalled();
	} );

	it( 'prefills the label of the subject', async () => {
		const wrapper = await openForSubject();

		expect( labelValue( wrapper ) ).toBe( 'Acme' );
	} );

	it( 'offers an editor holding the current statements of the subject', async () => {
		const wrapper = await openForSubject();

		const editor = wrapper.findComponent( SubjectEditor );
		expect( editor.props( 'statements' ) ).toEqual( companySchema.statementsFrom( company.getStatements() ) );
	} );

	it( 'saves the edited label', async () => {
		const wrapper = await openForSubject();
		await typeLabel( wrapper, 'Acme Holdings' );

		await save( wrapper );
		await flushPromises();

		expect( savedSubject().getLabel() ).toBe( 'Acme Holdings' );
	} );

	it( 'saves the edited statements', async () => {
		const wrapper = await openForSubject();
		await textInputAt( wrapper, 1 ).vm.$emit( 'update:modelValue', 'Acme Limited' );

		await save( wrapper );
		await flushPromises();

		expect( savedSubject().getStatements().get( new PropertyName( 'tradingName' ) ).value )
			.toEqual( newStringValue( 'Acme Limited' ) );
	} );

	it( 'keeps the identity of the subject it saves', async () => {
		const wrapper = await openForSubject();

		await save( wrapper );
		await flushPromises();

		expect( savedSubject().getId() ).toEqual( new SubjectId( SUBJECT_ID ) );
	} );

	it( 'trims the label', async () => {
		const wrapper = await openForSubject();
		await typeLabel( wrapper, '  Acme Holdings  ' );

		await save( wrapper );
		await flushPromises();

		expect( savedSubject().getLabel() ).toBe( 'Acme Holdings' );
	} );

	it( 'saves nothing when the label is blank', async () => {
		const wrapper = await openForSubject();
		await typeLabel( wrapper, '   ' );

		await save( wrapper );
		await flushPromises();

		expect( useSubjectStore().updateSubject ).not.toHaveBeenCalled();
	} );

	it( 'closes after saving', async () => {
		const wrapper = await openForSubject();

		await save( wrapper );
		await flushPromises();

		expect( state.open ).toBe( false );
		expect( state.subjectId ).toBeNull();
	} );

	it( 'stays open when saving fails', async () => {
		useSubjectStore().updateSubject = vi.fn().mockRejectedValue( new Error( 'Save failed' ) );
		const wrapper = await openForSubject();

		await save( wrapper );
		await flushPromises();

		expect( state.open ).toBe( true );
		expect( mw.notify ).toHaveBeenCalledWith( 'Save failed', { type: 'error' } );
	} );

	it( 'closes and reports when the subject cannot be loaded', async () => {
		useSubjectStore().getOrFetchSubject = vi.fn().mockRejectedValue( new Error( 'No such subject' ) );

		const wrapper = await openForSubject();

		expect( wrapper.findComponent( SubjectEditor ).exists() ).toBe( false );
		expect( state.open ).toBe( false );
		expect( mw.notify ).toHaveBeenCalledWith( 'No such subject', { type: 'error' } );
	} );

	it( 'closes when cancelled', async () => {
		const wrapper = await openForSubject();

		await wrapper.findComponent( CdxDialog ).vm.$emit( 'default' );

		expect( state.open ).toBe( false );
		expect( state.subjectId ).toBeNull();
	} );

	it( 'closes when dismissed', async () => {
		const wrapper = await openForSubject();

		await wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );

		expect( state.open ).toBe( false );
		expect( state.subjectId ).toBeNull();
	} );

	it( 'shows no previously loaded subject while the next one is loading', async () => {
		const wrapper = await openForSubject();
		await wrapper.findComponent( CdxDialog ).vm.$emit( 'default' );

		useSubjectStore().getOrFetchSubject = vi.fn().mockReturnValue( pendingForever() );
		state.subjectId = 's1demo5sssssss2';
		state.open = true;
		await flushPromises();

		expect( wrapper.findComponent( SubjectEditor ).exists() ).toBe( false );
	} );
} );
