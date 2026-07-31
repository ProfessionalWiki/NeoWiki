import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Infobox from '@/components/Views/Infobox.vue';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Statement } from '@/domain/Statement.ts';
import { createPropertyDefinitionFromJson, PropertyName } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { NumberType } from '@/domain/propertyTypes/Number.ts';
import { UrlType } from '@/domain/propertyTypes/Url.ts';
import { newNumberValue, newStringValue } from '@/domain/Value.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPinia, setActivePinia } from 'pinia';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Service } from '@/NeoWikiServices.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import { CdxButton } from '@wikimedia/codex';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

describe( 'Infobox', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'msg', 'config' ] } );
		( globalThis as any ).mw.util = {
			getUrl: vi.fn( ( title: string ) => `/wiki/${ title }` ),
		};
	} );

	let pinia: ReturnType<typeof createPinia>;
	let schemaStore: any;
	let subjectStore: any;
	const getSubjectMock = vi.fn();
	const getSchemaMock = vi.fn();

	const mockSchema = new Schema(
		'TestSchema',
		'A test schema',
		new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'name', { type: TextType.typeName } ),
			createPropertyDefinitionFromJson( 'age', { type: NumberType.typeName } ),
			createPropertyDefinitionFromJson( 'website', { type: UrlType.typeName } ),
		] ),
	);

	const mockSubject = new Subject(
		new SubjectId( 's1demo5sssssss1' ),
		'Test Subject',
		'TestSchema',
		new StatementList( [
			new Statement(
				new PropertyName( 'name' ), TextType.typeName, newStringValue( 'John Doe', 'Jane Doe' ),
			),
			new Statement(
				new PropertyName( 'age' ), NumberType.typeName, newNumberValue( 30 ),
			),
			new Statement(
				new PropertyName( 'website' ), UrlType.typeName, newStringValue( 'https://example.com' ),
			),
		] ),
	);

	const mountComponent = ( subject: Subject, canEditSubject: boolean ): VueWrapper => mount( Infobox, {
		props: {
			subjectId: subject.getId(),
			canEditSubject: canEditSubject,
		},
		global: {
			mocks: {
				$i18n,
			},
			plugins: [ pinia ],
			directives: {
				tooltip: {},
			},
			provide: {
				[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
				[ Service.SchemaPermissionHints ]: NeoWikiExtension.getInstance().newSchemaPermissionHints(),
				[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
				[ Service.SubjectRepository ]: { getSubject: getSubjectMock },
				[ Service.SchemaRepository ]: { getSchema: getSchemaMock },
			},
		},
	} );

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.setSchema( 'TestSchema', mockSchema );

		subjectStore = useSubjectStore();
		subjectStore.setSubject( mockSubject );
		subjectStore.validateSubjectUpdate = vi.fn().mockResolvedValue( [] );

		// openEditor reads through the injected repositories, not the stores the display renders from.
		getSubjectMock.mockReset();
		getSchemaMock.mockReset();
	} );

	it( 'renders the title correctly', () => {
		const wrapper = mountComponent( mockSubject, false );

		expect( wrapper.find( '.ext-neowiki-infobox__title' ).text() ).toBe( 'Test Subject' );
	} );

	it( 'renders statements correctly', () => {
		const wrapper = mountComponent( mockSubject, false );

		const schema = wrapper.find( '.ext-neowiki-infobox__schema' );
		expect( schema.text() ).toBe( 'TestSchema' );

		const statementElements = wrapper.findAll( '.ext-neowiki-infobox__item' );
		expect( statementElements ).toHaveLength( 3 ); // 3 properties + schema

		expect( statementElements[ 0 ].find( '.ext-neowiki-infobox__property' ).text() ).toBe( 'name' );
		expect( statementElements[ 0 ].find( '.ext-neowiki-infobox__value' ).text() ).toBe( 'John Doe, Jane Doe' );

		expect( statementElements[ 1 ].find( '.ext-neowiki-infobox__property' ).text() ).toBe( 'age' );
		expect( statementElements[ 1 ].find( '.ext-neowiki-infobox__value' ).text() ).toBe( '30' );

		expect( statementElements[ 2 ].find( '.ext-neowiki-infobox__property' ).text() ).toBe( 'website' );
		const linkElement = statementElements[ 2 ].find( '.ext-neowiki-infobox__value a' );
		expect( linkElement.attributes( 'href' ) ).toBe( 'https://example.com' );
		expect( linkElement.text() ).toBe( 'example.com' );
	} );

	it( 'renders without statements when subject has no statements', () => {
		const emptySubject = new Subject(
			new SubjectId( 's1demo6sssssss1' ),
			'Empty Subject',
			'TestSchema',
			new StatementList( [] ),
		);

		subjectStore.setSubject( emptySubject );

		const wrapper = mountComponent( emptySubject, false );

		const statementElements = wrapper.findAll( '.ext-neowiki-infobox__item' );
		expect( statementElements ).toHaveLength( 0 );
	} );

	it( 'does not render SubjectEditor button when canEditSubject is false', () => {
		const wrapper = mountComponent( mockSubject, false );

		expect( wrapper.findComponent( CdxButton ).exists() ).toBe( false );
	} );

	it( 'renders SubjectEditor button when canEditSubject is true', () => {
		const wrapper = mountComponent( mockSubject, true );

		const editButton = wrapper.findComponent( { name: 'CdxButton', props: { 'aria-label': 'neowiki-infobox-edit-link' } } );
		expect( editButton.exists() ).toBe( true );
	} );

	it( 'opens the dialog on the subject and schema fetched from the repositories', async () => {
		// Values the stores do not hold, so the assertions can only pass if the dialog was
		// handed the repositories' data rather than a registry read.
		const freshSubject = new Subject(
			mockSubject.getId(),
			'Fetched Subject',
			'TestSchema',
			new StatementList( [] ),
		);
		const freshSchema = new Schema( 'TestSchema', 'Fetched schema', new PropertyDefinitionList( [] ) );
		getSubjectMock.mockResolvedValue( freshSubject );
		getSchemaMock.mockResolvedValue( freshSchema );

		const wrapper = mountComponent( mockSubject, true );

		expect( wrapper.findComponent( SubjectEditorDialog ).exists() ).toBe( false );

		const editButton = wrapper.findComponent( { name: 'CdxButton', props: { 'aria-label': 'neowiki-infobox-edit-link' } } );
		await editButton.trigger( 'click' );
		await flushPromises();

		expect( getSubjectMock ).toHaveBeenCalledWith( mockSubject.getId() );
		expect( getSchemaMock ).toHaveBeenCalledWith( 'TestSchema' );
		const dialog = wrapper.findComponent( SubjectEditorDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'subject' ) ).toStrictEqual( freshSubject );
		expect( dialog.props( 'schema' ) ).toStrictEqual( freshSchema );
	} );

	it( 'renders schema name as a link to the Schema page', () => {
		const wrapper = mountComponent( mockSubject, false );

		const schemaLink = wrapper.find( '.ext-neowiki-infobox__schema a' );
		expect( schemaLink.text() ).toBe( 'TestSchema' );
		expect( schemaLink.attributes( 'href' ) ).toBe( '/wiki/Schema:TestSchema' );
	} );

	describe( 'publishing the editor-open fetch into the stores', () => {
		// This view renders from the stores, so what openEditor fetched has to land there too:
		// otherwise a property another session added stays unrenderable and a value saved against
		// it is invisible until reload.
		const schemaWithCostCentre = new Schema(
			'TestSchema',
			'A test schema',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Cost centre', { type: TextType.typeName } ),
			] ),
		);
		// The infobox renders a value only when the schema it holds defines that property, so a
		// subject carrying a value for a property the stale schema lacks renders as nothing.
		const subjectWithCostCentre = new Subject(
			mockSubject.getId(),
			'Test Subject',
			'TestSchema',
			new StatementList( [
				new Statement( new PropertyName( 'Cost centre' ), TextType.typeName, newStringValue( 'CC-42' ) ),
			] ),
		);
		const relabelledSubject = new Subject(
			mockSubject.getId(), 'Relabelled', 'TestSchema', new StatementList( [] ),
		);

		async function openEditor( wrapper: VueWrapper ): Promise<void> {
			await wrapper.findComponent(
				{ name: 'CdxButton', props: { 'aria-label': 'neowiki-infobox-edit-link' } },
			).trigger( 'click' );
			await flushPromises();
		}

		it( 'renders a value whose property only the fetched schema defines', async () => {
			getSubjectMock.mockResolvedValue( subjectWithCostCentre );
			getSchemaMock.mockResolvedValue( schemaWithCostCentre );

			const wrapper = mountComponent( mockSubject, true );
			await openEditor( wrapper );

			expect( schemaStore.getSchema( 'TestSchema' ) ).toStrictEqual( schemaWithCostCentre );
			expect( wrapper.text() ).toContain( 'Cost centre' );
			expect( wrapper.text() ).toContain( 'CC-42' );
		} );

		it( 'renders the fetched subject after the editor opens', async () => {
			getSubjectMock.mockResolvedValue( relabelledSubject );
			getSchemaMock.mockResolvedValue( mockSchema );

			const wrapper = mountComponent( mockSubject, true );
			await openEditor( wrapper );

			expect( wrapper.find( '.ext-neowiki-infobox__title' ).text() ).toBe( 'Relabelled' );
		} );

		it( 'discards the schema it fetched when a schema mutation lands mid-fetch', async () => {
			const saved = new Schema( 'TestSchema', 'Saved elsewhere', new PropertyDefinitionList( [] ) );
			let resolveSchema!: ( schema: Schema ) => void;
			getSubjectMock.mockResolvedValue( mockSubject );
			getSchemaMock.mockReturnValue( new Promise<Schema>( ( resolve ) => {
				resolveSchema = resolve;
			} ) );

			const wrapper = mountComponent( mockSubject, true );
			await openEditor( wrapper );

			schemaStore.mutationEpoch++;
			schemaStore.setSchema( 'TestSchema', saved );
			resolveSchema( schemaWithCostCentre );
			await flushPromises();

			expect( schemaStore.getSchema( 'TestSchema' ) ).toStrictEqual( saved );
		} );

		it( 'discards the subject it fetched when a subject mutation lands mid-fetch', async () => {
			const saved = new Subject(
				mockSubject.getId(), 'Saved elsewhere', 'TestSchema', new StatementList( [] ),
			);
			let resolveSubject!: ( subject: Subject ) => void;
			getSchemaMock.mockResolvedValue( mockSchema );
			getSubjectMock.mockReturnValue( new Promise<Subject>( ( resolve ) => {
				resolveSubject = resolve;
			} ) );

			const wrapper = mountComponent( mockSubject, true );
			await openEditor( wrapper );

			subjectStore.mutationEpoch++;
			subjectStore.setSubject( saved );
			resolveSubject( relabelledSubject );
			await flushPromises();

			expect( subjectStore.getSubject( mockSubject.getId() ) ).toStrictEqual( saved );
		} );
	} );
} );
