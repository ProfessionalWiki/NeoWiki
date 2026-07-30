import './../neowiki-test-env.ts';
import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import type { Pinia } from 'pinia';
import { ref, Ref } from 'vue';
import { CdxDialog, CdxTextInput } from '@wikimedia/codex';
import CreateChildDialog from '@redherb/createChild/CreateChildDialog.vue';
import createChildConstants from '@redherb/createChild/constants.js';
import { createPropertyDefinitionFromJson, PropertyName } from '@/domain/PropertyDefinition.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { Schema } from '@/domain/Schema.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { newStringValue } from '@/domain/Value.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import { NeoWikiTestServices } from '../NeoWikiTestServices.ts';
import { createI18nMock, setupMwMock } from '../VueTestHelpers.ts';
import { loadRedHerbFrontend } from './RedHerbRegistration.ts';

const DIALOG_OPEN_KEY = createChildConstants.DIALOG_OPEN_KEY;

const SCHEMA_NAME = 'Company';
const PAGE_ID = 42;

const companySchema = new Schema(
	SCHEMA_NAME,
	'A company',
	new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'tradingName', { type: TextType.typeName } ),
	] ),
);

describe( 'CreateChildDialog', () => {
	let pinia: Pinia;
	let open: Ref<boolean>;

	beforeAll( async () => {
		await loadRedHerbFrontend();
	} );

	beforeEach( () => {
		setupMwMock( { config: { wgArticleId: PAGE_ID } } );
		( globalThis as any ).mw.log = { error: vi.fn() };

		pinia = createPinia();
		setActivePinia( pinia );

		useSchemaStore().getOrFetchSchema = vi.fn().mockResolvedValue( companySchema );
		useSubjectStore().createChildSubject = vi.fn().mockResolvedValue( undefined );

		open = ref( false );
	} );

	function newWrapper(): VueWrapper {
		return mount( CreateChildDialog, {
			global: {
				plugins: [ pinia ],
				provide: {
					...NeoWikiTestServices.getServices(),
					[ DIALOG_OPEN_KEY ]: open,
				},
				directives: { tooltip: {} },
				mocks: { $i18n: createI18nMock() },
				stubs: { teleport: true },
			},
		} );
	}

	async function openDialog(): Promise<VueWrapper> {
		const wrapper = newWrapper();
		open.value = true;
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

	it( 'loads the schema of the subjects it creates when opened', async () => {
		await openDialog();

		expect( useSchemaStore().getOrFetchSchema ).toHaveBeenCalledWith( SCHEMA_NAME );
	} );

	it( 'does not load the schema before it is opened', async () => {
		newWrapper();
		await flushPromises();

		expect( useSchemaStore().getOrFetchSchema ).not.toHaveBeenCalled();
	} );

	it( 'loads the schema only once across openings', async () => {
		const wrapper = await openDialog();

		open.value = false;
		await flushPromises();
		open.value = true;
		await flushPromises();

		expect( useSchemaStore().getOrFetchSchema ).toHaveBeenCalledTimes( 1 );
		expect( wrapper.findComponent( SubjectEditor ).exists() ).toBe( true );
	} );

	it( 'offers an editor for the blank statements of the schema', async () => {
		const wrapper = await openDialog();

		const editor = wrapper.findComponent( SubjectEditor );
		expect( editor.props( 'statements' ) ).toEqual( companySchema.blankStatements() );
		expect( editor.props( 'schema' ) ).toBe( companySchema );
	} );

	it( 'creates a child subject of the current page from the label and the edited statements', async () => {
		const wrapper = await openDialog();
		await typeLabel( wrapper, 'Acme' );

		await save( wrapper );
		await flushPromises();

		expect( useSubjectStore().createChildSubject ).toHaveBeenCalledWith(
			PAGE_ID,
			'Acme',
			SCHEMA_NAME,
			expect.any( StatementList ),
		);
	} );

	it( 'passes on the values entered into the editor', async () => {
		const wrapper = await openDialog();
		await typeLabel( wrapper, 'Acme' );
		await textInputAt( wrapper, 1 ).vm.$emit( 'update:modelValue', 'Acme Corporation' );

		await save( wrapper );
		await flushPromises();

		const statements = vi.mocked( useSubjectStore().createChildSubject ).mock.calls[ 0 ][ 3 ];
		expect( statements.get( new PropertyName( 'tradingName' ) ).value ).toEqual( newStringValue( 'Acme Corporation' ) );
	} );

	it( 'trims the label', async () => {
		const wrapper = await openDialog();
		await typeLabel( wrapper, '  Acme  ' );

		await save( wrapper );
		await flushPromises();

		expect( useSubjectStore().createChildSubject ).toHaveBeenCalledWith(
			PAGE_ID,
			'Acme',
			SCHEMA_NAME,
			expect.anything(),
		);
	} );

	it( 'creates nothing when the label is blank', async () => {
		const wrapper = await openDialog();
		await typeLabel( wrapper, '   ' );

		await save( wrapper );
		await flushPromises();

		expect( useSubjectStore().createChildSubject ).not.toHaveBeenCalled();
	} );

	it( 'closes after creating the subject', async () => {
		const wrapper = await openDialog();
		await typeLabel( wrapper, 'Acme' );

		await save( wrapper );
		await flushPromises();

		expect( open.value ).toBe( false );
	} );

	it( 'stays open when creating the subject fails', async () => {
		useSubjectStore().createChildSubject = vi.fn().mockRejectedValue( new Error( 'Save failed' ) );
		const wrapper = await openDialog();
		await typeLabel( wrapper, 'Acme' );

		await save( wrapper );
		await flushPromises();

		expect( open.value ).toBe( true );
		expect( mw.notify ).toHaveBeenCalledWith( 'redherb-create-child-error', { type: 'error' } );
	} );

	it( 'closes and reports when the schema cannot be loaded', async () => {
		useSchemaStore().getOrFetchSchema = vi.fn().mockRejectedValue( new Error( 'No such schema' ) );

		const wrapper = await openDialog();

		expect( wrapper.findComponent( SubjectEditor ).exists() ).toBe( false );
		expect( open.value ).toBe( false );
		expect( mw.notify ).toHaveBeenCalledWith( 'No such schema', { type: 'error' } );
	} );

	it( 'closes when cancelled', async () => {
		const wrapper = await openDialog();

		await wrapper.findComponent( CdxDialog ).vm.$emit( 'default' );

		expect( open.value ).toBe( false );
	} );

	it( 'forgets the previous label when reopened', async () => {
		const wrapper = await openDialog();
		await typeLabel( wrapper, 'Acme' );

		open.value = false;
		await flushPromises();
		open.value = true;
		await flushPromises();

		expect( labelValue( wrapper ) ).toBe( '' );
	} );
} );
