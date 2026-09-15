import './../neowiki-test-env.ts';
import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import type { Pinia } from 'pinia';
import { CdxTextInput } from '@wikimedia/codex';
import SubjectFinderPanel from '@redherb/subjectFinder/SubjectFinderPanel.vue';
import Infobox from '@/components/Views/Infobox.vue';
import SubjectLookup from '@/components/common/SubjectLookup.vue';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
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
import { NeoWikiTestServices } from '../NeoWikiTestServices.ts';
import { createI18nMock, setupMwMock } from '../VueTestHelpers.ts';
import { loadRedHerbFrontend } from './RedHerbRegistration.ts';

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

describe( 'SubjectFinderPanel', () => {
	let pinia: Pinia;
	let loadSubjectsAndSchemas: ReturnType<typeof vi.fn>;

	beforeAll( async () => {
		await loadRedHerbFrontend();
	} );

	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'msg', 'config', 'notify', 'util' ] } );
		( globalThis as any ).mw.log = { error: vi.fn() };

		pinia = createPinia();
		setActivePinia( pinia );

		useSchemaStore().setSchema( SCHEMA_NAME, companySchema );
		useSubjectStore().setSubject( company );

		loadSubjectsAndSchemas = vi.fn().mockResolvedValue( undefined );
		vi.spyOn( NeoWikiExtension.getInstance(), 'getStoreStateLoader' )
			.mockReturnValue( { loadSubjectsAndSchemas: loadSubjectsAndSchemas } as any );
	} );

	function newWrapper(): VueWrapper {
		return mount( SubjectFinderPanel, {
			global: {
				plugins: [ pinia ],
				provide: NeoWikiTestServices.getServices(),
				directives: { tooltip: {} },
				mocks: { $i18n: createI18nMock() },
				stubs: { teleport: true },
			},
		} );
	}

	async function typeSchemaName( wrapper: VueWrapper, name: string ): Promise<void> {
		const schemaInput = wrapper.findComponent( CdxTextInput );

		await schemaInput.vm.$emit( 'update:modelValue', name );
	}

	async function selectSubject( wrapper: VueWrapper, subjectId: string | null ): Promise<void> {
		await wrapper.findComponent( SubjectLookup ).vm.$emit( 'update:selected', subjectId );
	}

	it( 'offers no subject lookup before a schema is named', () => {
		const wrapper = newWrapper();

		expect( wrapper.findComponent( SubjectLookup ).exists() ).toBe( false );
	} );

	it( 'offers a subject lookup once a schema is named', async () => {
		const wrapper = newWrapper();

		await typeSchemaName( wrapper, SCHEMA_NAME );

		expect( wrapper.findComponent( SubjectLookup ).exists() ).toBe( true );
	} );

	it( 'limits the lookup to subjects of the named schema', async () => {
		const wrapper = newWrapper();

		await typeSchemaName( wrapper, SCHEMA_NAME );

		expect( wrapper.findComponent( SubjectLookup ).props( 'targetSchema' ) ).toBe( SCHEMA_NAME );
	} );

	it( 'ignores surrounding whitespace in the schema name', async () => {
		const wrapper = newWrapper();

		await typeSchemaName( wrapper, `  ${ SCHEMA_NAME }  ` );

		expect( wrapper.findComponent( SubjectLookup ).props( 'targetSchema' ) ).toBe( SCHEMA_NAME );
	} );

	it( 'offers no subject lookup for a schema name of only whitespace', async () => {
		const wrapper = newWrapper();

		await typeSchemaName( wrapper, '   ' );

		expect( wrapper.findComponent( SubjectLookup ).exists() ).toBe( false );
	} );

	it( 'renders nothing until a subject is selected', async () => {
		const wrapper = newWrapper();

		await typeSchemaName( wrapper, SCHEMA_NAME );

		expect( wrapper.findComponent( Infobox ).exists() ).toBe( false );
	} );

	it( 'loads the data of the selected subject', async () => {
		const wrapper = newWrapper();
		await typeSchemaName( wrapper, SCHEMA_NAME );

		await selectSubject( wrapper, SUBJECT_ID );
		await flushPromises();

		expect( loadSubjectsAndSchemas ).toHaveBeenCalledWith( new Set( [ SUBJECT_ID ] ) );
	} );

	it( 'renders the selected subject once its data is loaded', async () => {
		const wrapper = newWrapper();
		await typeSchemaName( wrapper, SCHEMA_NAME );

		await selectSubject( wrapper, SUBJECT_ID );
		await flushPromises();

		expect( wrapper.findComponent( Infobox ).exists() ).toBe( true );
		expect( wrapper.find( '.ext-redherb-subject-finder__rendered' ).text() ).toContain( 'Acme' );
	} );

	it( 'renders the subject as read-only', async () => {
		const wrapper = newWrapper();
		await typeSchemaName( wrapper, SCHEMA_NAME );

		await selectSubject( wrapper, SUBJECT_ID );
		await flushPromises();

		expect( wrapper.findComponent( Infobox ).props( 'canEditSubject' ) ).toBe( false );
	} );

	it( 'waits for the data before rendering the subject', async () => {
		loadSubjectsAndSchemas.mockReturnValue( pendingForever() );
		const wrapper = newWrapper();
		await typeSchemaName( wrapper, SCHEMA_NAME );

		await selectSubject( wrapper, SUBJECT_ID );
		await flushPromises();

		expect( wrapper.findComponent( Infobox ).exists() ).toBe( false );
	} );

	it( 'stops rendering a subject when the selection is cleared', async () => {
		const wrapper = newWrapper();
		await typeSchemaName( wrapper, SCHEMA_NAME );
		await selectSubject( wrapper, SUBJECT_ID );
		await flushPromises();

		await selectSubject( wrapper, null );
		await flushPromises();

		expect( wrapper.findComponent( Infobox ).exists() ).toBe( false );
	} );

	it( 'reports a failure to load the subject instead of rendering it', async () => {
		loadSubjectsAndSchemas.mockRejectedValue( new Error( 'Network is down' ) );
		const wrapper = newWrapper();
		await typeSchemaName( wrapper, SCHEMA_NAME );

		await selectSubject( wrapper, SUBJECT_ID );
		await flushPromises();

		expect( wrapper.findComponent( Infobox ).exists() ).toBe( false );
		expect( mw.notify ).toHaveBeenCalledWith( 'Network is down', { type: 'error' } );
	} );
} );
