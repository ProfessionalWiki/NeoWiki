import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AutomaticInfobox from '@/components/Views/AutomaticInfobox.vue';
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
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import { CdxButton } from '@wikimedia/codex';
import { setupMwMock } from '../../VueTestHelpers.ts';

const $i18n = vi.fn().mockImplementation( ( key ) => ( {
	text: () => key,
} ) );

describe( 'AutomaticInfobox', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'msg' ] } );
		// Mock mw.util.getUrl
		( global as any ).mw = {
			...( global as any ).mw,
			util: {
				getUrl: vi.fn( ( title: string ) => `/wiki/${ title }` ),
			},
		};
	} );

	let pinia: ReturnType<typeof createPinia>;
	let schemaStore;
	let subjectStore: any;

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

	const mountComponent = ( subject: Subject, canEditSubject: boolean ): VueWrapper => mount( AutomaticInfobox, {
		props: {
			subjectId: subject.getId(),
			canEditSubject: canEditSubject,
		},
		global: {
			mocks: {
				$i18n,
			},
			plugins: [ pinia ],
			provide: {
				[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
				[ Service.SchemaAuthorizer ]: NeoWikiExtension.getInstance().newSchemaAuthorizer(),
				[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
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
	} );

	it( 'renders the title correctly', () => {
		const wrapper = mountComponent( mockSubject, false );

		expect( wrapper.find( '.ext-neowiki-auto-infobox__title' ).text() ).toBe( 'Test Subject' );
	} );

	it( 'renders statements correctly', () => {
		const wrapper = mountComponent( mockSubject, false );

		const schema = wrapper.find( '.ext-neowiki-auto-infobox__schema' );
		expect( schema.text() ).toBe( 'TestSchema' );

		const statementElements = wrapper.findAll( '.ext-neowiki-auto-infobox__item' );
		expect( statementElements ).toHaveLength( 3 ); // 3 properties + schema

		expect( statementElements[ 0 ].find( '.ext-neowiki-auto-infobox__property' ).text() ).toBe( 'name' );
		expect( statementElements[ 0 ].find( '.ext-neowiki-auto-infobox__value' ).text() ).toBe( 'John Doe, Jane Doe' );

		expect( statementElements[ 1 ].find( '.ext-neowiki-auto-infobox__property' ).text() ).toBe( 'age' );
		expect( statementElements[ 1 ].find( '.ext-neowiki-auto-infobox__value' ).text() ).toBe( '30' );

		expect( statementElements[ 2 ].find( '.ext-neowiki-auto-infobox__property' ).text() ).toBe( 'website' );
		const linkElement = statementElements[ 2 ].find( '.ext-neowiki-auto-infobox__value a' );
		expect( linkElement.attributes( 'href' ) ).toBe( 'https://example.com' );
		expect( linkElement.text() ).toBe( 'https://example.com' );
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

		const statementElements = wrapper.findAll( '.ext-neowiki-auto-infobox__item' );
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

	it( 'opens the SubjectEditorDialog when edit button is clicked', async () => {
		const wrapper = mountComponent( mockSubject, true );

		const dialog = wrapper.findComponent( SubjectEditorDialog );
		expect( dialog.props( 'open' ) ).toBe( false );

		const editButton = wrapper.findComponent( { name: 'CdxButton', props: { 'aria-label': 'neowiki-infobox-edit-link' } } );
		await editButton.trigger( 'click' );

		expect( dialog.props( 'open' ) ).toBe( true );
	} );

	it( 'renders schema name as a clickable link when user has edit permissions', async () => {
		const schemaAuthorizer = {
			canEditSchema: vi.fn().mockResolvedValue( true ),
		};

		const wrapper = mount( AutomaticInfobox, {
			props: {
				subjectId: mockSubject.getId(),
				canEditSubject: false,
			},
			global: {
				mocks: {
					$i18n,
				},
				plugins: [ pinia ],
				provide: {
					[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
					[ Service.SchemaAuthorizer ]: schemaAuthorizer,
					[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
				},
			},
		} );

		// Wait for the async permission check to complete
		await flushPromises();

		const schemaLinks = wrapper.findAll( '.ext-neowiki-auto-infobox__schema a' );
		expect( schemaLinks.length ).toBeGreaterThan( 0 );
		const schemaLink = schemaLinks[ 0 ];
		expect( schemaLink.text() ).toBe( 'TestSchema' );
		expect( schemaLink.attributes( 'href' ) ).toBe( '#' );
		expect( schemaLink.attributes( 'role' ) ).toBe( 'button' );
	} );

	it( 'opens the SchemaEditorDialog when schema link is clicked', async () => {
		const schemaAuthorizer = {
			canEditSchema: vi.fn().mockResolvedValue( true ),
		};

		const wrapper = mount( AutomaticInfobox, {
			props: {
				subjectId: mockSubject.getId(),
				canEditSubject: false,
			},
			global: {
				mocks: {
					$i18n,
				},
				plugins: [ pinia ],
				provide: {
					[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
					[ Service.SchemaAuthorizer ]: schemaAuthorizer,
					[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
				},
				stubs: {
					teleport: true,
				},
			},
		} );

		// Wait for the async permission check to complete
		await flushPromises();

		const schemaLink = wrapper.find( '.ext-neowiki-auto-infobox__schema a' );
		await schemaLink.trigger( 'click' );

		// SchemaEditorDialog should be rendered and open
		const schemaDialog = wrapper.findComponent( SchemaEditorDialog );
		expect( schemaDialog.exists() ).toBe( true );
		expect( schemaDialog.props( 'open' ) ).toBe( true );
	} );

	it( 'renders schema name as a link to Schema page when user lacks edit permissions', async () => {
		const schemaAuthorizer = {
			canEditSchema: vi.fn().mockResolvedValue( false ),
		};

		// Ensure subject is in the store
		subjectStore.setSubject( mockSubject );

		const wrapper = mount( AutomaticInfobox, {
			props: {
				subjectId: mockSubject.getId(),
				canEditSubject: false,
			},
			global: {
				mocks: {
					$i18n,
				},
				plugins: [ pinia ],
				provide: {
					[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
					[ Service.SchemaAuthorizer ]: schemaAuthorizer,
					[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
				},
			},
		} );

		// Wait for the async permission check to complete
		await flushPromises();

		const schemaLinks = wrapper.findAll( '.ext-neowiki-auto-infobox__schema a' );
		expect( schemaLinks.length ).toBeGreaterThan( 0 );
		const schemaLink = schemaLinks[ 0 ];
		expect( schemaLink.text() ).toBe( 'TestSchema' );
		// The href should be a link to the Schema: page
		expect( schemaLink.attributes( 'href' ) ).toContain( 'Schema:TestSchema' );
	} );
} );
