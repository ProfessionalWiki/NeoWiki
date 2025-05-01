import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AutomaticInfobox from '@/components/Views/AutomaticInfobox.vue';
import { Subject } from '@neo/domain/Subject.ts';
import { SubjectId } from '@neo/domain/SubjectId.ts';
import { StatementList } from '@neo/domain/StatementList.ts';
import { Statement } from '@neo/domain/Statement.ts';
import { createPropertyDefinitionFromJson, PropertyName } from '@neo/domain/PropertyDefinition.ts';
import { TextType } from '@neo/domain/propertyTypes/Text.ts';
import { NumberType } from '@neo/domain/propertyTypes/Number.ts';
import { UrlType } from '@neo/domain/propertyTypes/Url.ts';
import { newNumberValue, newStringValue } from '@neo/domain/Value.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Schema } from '@neo/domain/Schema.ts';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';
import { createPinia, setActivePinia } from 'pinia';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Service } from '@/NeoWikiServices.ts';

const $i18n = vi.fn().mockImplementation( ( key ) => ( {
	text: () => key
} ) );

describe( 'AutomaticInfobox', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	let pinia: ReturnType<typeof createPinia>;
	let schemaStore;

	const mockSchema = new Schema(
		'TestSchema',
		'A test schema',
		new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'name', { type: 'string', format: TextType.typeName } ),
			createPropertyDefinitionFromJson( 'age', { type: 'number', format: NumberType.typeName } ),
			createPropertyDefinitionFromJson( 'website', { type: 'string', format: UrlType.typeName } )
		] )
	);

	const mockSubject = new Subject(
		new SubjectId( 's1demo5sssssss1' ),
		'Test Subject',
		'TestSchema',
		new StatementList( [
			new Statement(
				new PropertyName( 'name' ), TextType.typeName, newStringValue( 'John Doe', 'Jane Doe' )
			),
			new Statement(
				new PropertyName( 'age' ), NumberType.typeName, newNumberValue( 30 )
			),
			new Statement(
				new PropertyName( 'website' ), UrlType.typeName, newStringValue( 'https://example.com' )
			)
		] )
	);

	const mountComponent = ( subject: Subject, schema: Schema, canEditSubject: boolean ): VueWrapper => mount( AutomaticInfobox, {
		props: {
			subject: subject,
			schema: schema,
			canEditSubject: canEditSubject
		},
		global: {
			mocks: {
				$i18n
			},
			plugins: [ pinia ],
			provide: {
				[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
				[ Service.SchemaAuthorizer ]: NeoWikiExtension.getInstance().newSchemaAuthorizer()
			}
		}
	} );

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );
		schemaStore = useSchemaStore();
		schemaStore.setSchema( 'TestSchema', mockSchema );
	} );

	it( 'renders the title correctly', () => {
		const wrapper = mountComponent( mockSubject, mockSchema, false );

		expect( wrapper.find( '.ext-neowiki-auto-infobox__title' ).text() ).toBe( 'Test Subject' );
	} );

	it( 'renders statements correctly', () => {
		const wrapper = mountComponent( mockSubject, mockSchema, false );

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
			new StatementList( [] )
		);

		const wrapper = mountComponent( emptySubject, mockSchema, false );

		const statementElements = wrapper.findAll( '.ext-neowiki-auto-infobox__item' );
		expect( statementElements ).toHaveLength( 0 );
	} );

	it( 'does not render SubjectEditor when canEditSubject is false', () => {
		const wrapper = mountComponent( mockSubject, mockSchema, false );

		expect( wrapper.find( '.ext-neowiki-subject-editor-container' ).exists() ).toBe( false );
	} );

	it( 'renders SubjectEditor when canEditSubject is true', () => {
		const wrapper = mountComponent( mockSubject, mockSchema, true );

		const editButton = wrapper.find( '.ext-neowiki-subject-editor-container' );
		expect( editButton.exists() ).toBe( true );
	} );
} );
