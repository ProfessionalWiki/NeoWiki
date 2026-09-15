import './../neowiki-test-env.ts';
import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import type { Pinia } from 'pinia';
import RedHerbCard from '@redherb/RedHerbCard.vue';
import { Layout } from '@/domain/Layout.ts';
import { PropertyName, createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { Schema } from '@/domain/Schema.ts';
import { Statement } from '@/domain/Statement.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { newStringValue } from '@/domain/Value.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { useLayoutStore } from '@/stores/LayoutStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import { NeoWikiTestServices } from '../NeoWikiTestServices.ts';
import { createI18nMock, setupMwMock } from '../VueTestHelpers.ts';
import { COLOR_TYPE_NAME, loadRedHerbFrontend } from './RedHerbRegistration.ts';

const SCHEMA_NAME = 'Herb';
const LAYOUT_NAME = 'HerbCard';
const SUBJECT_ID = new SubjectId( 's1demo5sssssss1' );

const TERM = '.ext-redherb-card__term';
const VALUE = '.ext-redherb-card__value';
const COLUMN = '.ext-redherb-card__grid';
const WIDE_FIELD = '.ext-redherb-card__wide-field';

const schema = new Schema(
	SCHEMA_NAME,
	'A herb',
	new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'commonName', { type: TextType.typeName } ),
		createPropertyDefinitionFromJson( 'flowerColor', { type: COLOR_TYPE_NAME } ),
		createPropertyDefinitionFromJson( 'notes', { type: TextType.typeName } ),
	] ),
);

const subject = new Subject(
	SUBJECT_ID,
	'Red Clover',
	SCHEMA_NAME,
	new StatementList( [
		new Statement( new PropertyName( 'commonName' ), TextType.typeName, newStringValue( 'Red Clover' ) ),
		new Statement( new PropertyName( 'flowerColor' ), COLOR_TYPE_NAME, newStringValue( '#ff5733' ) ),
		new Statement( new PropertyName( 'notes' ), TextType.typeName, newStringValue( 'Grows in meadows' ) ),
	] ),
);

function newLayout( settings: Record<string, unknown> ): Layout {
	return new Layout( LAYOUT_NAME, SCHEMA_NAME, 'redherb-card', '', [], settings );
}

describe( 'RedHerbCard', () => {
	let pinia: Pinia;

	beforeAll( async () => {
		await loadRedHerbFrontend();
	} );

	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'msg', 'config', 'notify', 'util' ] } );

		pinia = createPinia();
		setActivePinia( pinia );

		useSchemaStore().setSchema( SCHEMA_NAME, schema );
		useSubjectStore().setSubject( subject );
	} );

	function newWrapper( canEditSubject = false, layoutName: string | undefined = undefined ): VueWrapper {
		return mount( RedHerbCard, {
			props: {
				subjectId: SUBJECT_ID,
				canEditSubject: canEditSubject,
				layoutName: layoutName,
			},
			global: {
				plugins: [ pinia ],
				provide: NeoWikiTestServices.getServices(),
				directives: { tooltip: {} },
				mocks: { $i18n: createI18nMock() },
			},
		} );
	}

	function termsIn( wrapper: VueWrapper, selector: string ): string[] {
		return wrapper.findAll( `${ selector } ${ TERM }` ).map( ( term ) => term.text() );
	}

	it( 'renders the subject label', () => {
		const wrapper = newWrapper();

		expect( wrapper.find( '.ext-redherb-card__label' ).text() ).toBe( 'Red Clover' );
	} );

	it( 'renders a term for every property with a value', () => {
		const wrapper = newWrapper();

		expect( wrapper.findAll( TERM ).map( ( term ) => term.text() ) )
			.toEqual( [ 'commonName', 'flowerColor', 'notes' ] );
	} );

	it( 'renders each value with the display component of its property type', () => {
		const wrapper = newWrapper();

		const values = wrapper.findAll( VALUE );
		expect( values[ 0 ].text() ).toBe( 'Red Clover' );
		expect( values[ 1 ].find( '.ext-redherb-color-display__swatch' ).exists() ).toBe( true );
		expect( values[ 1 ].text() ).toBe( '#ff5733' );
	} );

	it( 'omits properties the subject has no value for', () => {
		const withoutNotes = new Subject(
			SUBJECT_ID,
			'Red Clover',
			SCHEMA_NAME,
			new StatementList( [
				new Statement( new PropertyName( 'commonName' ), TextType.typeName, newStringValue( 'Red Clover' ) ),
			] ),
		);
		useSubjectStore().setSubject( withoutNotes );

		const wrapper = newWrapper();

		expect( wrapper.findAll( TERM ).map( ( term ) => term.text() ) ).toEqual( [ 'commonName' ] );
	} );

	it( 'spreads the properties over two columns', () => {
		const wrapper = newWrapper();

		const columns = wrapper.findAll( COLUMN );
		expect( columns ).toHaveLength( 2 );
		expect( termsIn( wrapper, COLUMN ) ).toEqual( [ 'commonName', 'flowerColor', 'notes' ] );
		expect( columns[ 0 ].findAll( TERM ) ).toHaveLength( 2 );
	} );

	it( 'renders the properties the layout marks full width outside the columns', () => {
		useLayoutStore().setLayout( LAYOUT_NAME, newLayout( { fullWidthProperties: [ 'notes' ] } ) );

		const wrapper = newWrapper( false, LAYOUT_NAME );

		expect( termsIn( wrapper, WIDE_FIELD ) ).toEqual( [ 'notes' ] );
		expect( termsIn( wrapper, COLUMN ) ).toEqual( [ 'commonName', 'flowerColor' ] );
	} );

	it( 'renders no full-width section when the layout does not ask for one', () => {
		useLayoutStore().setLayout( LAYOUT_NAME, newLayout( {} ) );

		const wrapper = newWrapper( false, LAYOUT_NAME );

		expect( wrapper.findAll( WIDE_FIELD ) ).toHaveLength( 0 );
		expect( termsIn( wrapper, COLUMN ) ).toEqual( [ 'commonName', 'flowerColor', 'notes' ] );
	} );

	it( 'ignores a full-width setting that is not a list of property names', () => {
		useLayoutStore().setLayout( LAYOUT_NAME, newLayout( { fullWidthProperties: 'notes' } ) );

		const wrapper = newWrapper( false, LAYOUT_NAME );

		expect( wrapper.findAll( WIDE_FIELD ) ).toHaveLength( 0 );
	} );

	it( 'links to the schema page of the subject', () => {
		const wrapper = newWrapper();

		expect( wrapper.find( `a[href="/wiki/Schema:${ SCHEMA_NAME }"]` ).exists() ).toBe( true );
	} );

	it( 'links to the layout page when rendered through a layout', () => {
		useLayoutStore().setLayout( LAYOUT_NAME, newLayout( {} ) );

		const wrapper = newWrapper( false, LAYOUT_NAME );

		expect( wrapper.find( `a[href="/wiki/Layout:${ LAYOUT_NAME }"]` ).exists() ).toBe( true );
	} );

	it( 'does not link to a layout page when rendered without a layout', () => {
		const wrapper = newWrapper();

		expect( wrapper.find( 'a[href^="/wiki/Layout:"]' ).exists() ).toBe( false );
	} );

	it( 'offers no editor to a user who may not edit the subject', () => {
		const wrapper = newWrapper( false );

		expect( wrapper.findComponent( SubjectEditorDialog ).exists() ).toBe( false );
	} );

	it( 'keeps the editor closed until the edit button is used', () => {
		const wrapper = newWrapper( true );

		expect( wrapper.findComponent( SubjectEditorDialog ).props( 'open' ) ).toBe( false );
	} );

	it( 'refetches the subject and its schema before opening the editor', async () => {
		const subjectStore = useSubjectStore();
		const schemaStore = useSchemaStore();
		subjectStore.fetchSubject = vi.fn().mockResolvedValue( undefined );
		schemaStore.fetchSchema = vi.fn().mockResolvedValue( undefined );

		const wrapper = newWrapper( true );
		await wrapper.find( '.ext-redherb-card__actions button' ).trigger( 'click' );
		await flushPromises();

		expect( subjectStore.fetchSubject ).toHaveBeenCalledWith( SUBJECT_ID );
		expect( schemaStore.fetchSchema ).toHaveBeenCalledWith( SCHEMA_NAME );
		expect( wrapper.findComponent( SubjectEditorDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'reports a failure to load the latest data instead of opening the editor', async () => {
		const subjectStore = useSubjectStore();
		subjectStore.fetchSubject = vi.fn().mockRejectedValue( new Error( 'Network is down' ) );
		useSchemaStore().fetchSchema = vi.fn().mockResolvedValue( undefined );

		const wrapper = newWrapper( true );
		await wrapper.find( '.ext-redherb-card__actions button' ).trigger( 'click' );
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith( 'Network is down', { type: 'error' } );
		expect( wrapper.findComponent( SubjectEditorDialog ).props( 'open' ) ).toBe( false );
	} );
} );
