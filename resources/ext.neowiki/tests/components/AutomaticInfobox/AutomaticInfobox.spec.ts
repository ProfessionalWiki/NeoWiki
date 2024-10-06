import { mount, VueWrapper } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import AutomaticInfobox from '@/components/AutomaticInfobox/AutomaticInfobox.vue';
import { Subject } from '@neo/domain/Subject';
import { SubjectId } from '@neo/domain/SubjectId';
import { StatementList } from '@neo/domain/StatementList';
import { Statement } from '@neo/domain/Statement';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { TextFormat } from '@neo/domain/valueFormats/Text';
import { NumberFormat } from '@neo/domain/valueFormats/Number';
import { UrlFormat } from '@neo/domain/valueFormats/Url';
import { newStringValue, newNumberValue } from '@neo/domain/Value';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { Schema } from '@neo/domain/Schema';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';
import { createPropertyDefinitionFromJson } from '@neo/domain/PropertyDefinition';
import { setActivePinia, createPinia } from 'pinia';
import { useSchemaStore } from '@/stores/SchemaStore';
import { Service } from '@/NeoWikiServices.ts';

const $i18n = vi.fn().mockImplementation( ( key ) => ( {
	text: () => key
} ) );

describe( 'AutomaticInfobox', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaStore;

	const mockSchema = new Schema(
		'TestSchema',
		'A test schema',
		new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'name', { type: 'string', format: TextFormat.formatName } ),
			createPropertyDefinitionFromJson( 'age', { type: 'number', format: NumberFormat.formatName } ),
			createPropertyDefinitionFromJson( 'website', { type: 'string', format: UrlFormat.formatName } )
		] )
	);

	const mockSubject = new Subject(
		new SubjectId( 's1demo5sssssss1' ),
		'Test Subject',
		'TestSchema',
		new StatementList( [
			new Statement(
				new PropertyName( 'name' ), TextFormat.formatName, newStringValue( 'John Doe', 'Jane Doe' )
			),
			new Statement(
				new PropertyName( 'age' ), NumberFormat.formatName, newNumberValue( 30 )
			),
			new Statement(
				new PropertyName( 'website' ), UrlFormat.formatName, newStringValue( 'https://example.com' )
			)
		] ),
		new PageIdentifiers( 1, 'Test_Subject' )
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
			provide: {
				[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getFormatSpecificComponentRegistry(),
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

		expect( wrapper.find( '.auto-infobox__title' ).text() ).toBe( 'Test Subject' );
	} );

	it( 'renders statements correctly', () => {
		const wrapper = mountComponent( mockSubject, mockSchema, false );

		const statementElements = wrapper.findAll( '.auto-infobox__item' );
		expect( statementElements ).toHaveLength( 4 ); // 3 properties + schema

		expect( statementElements[ 0 ].find( '.auto-infobox__property' ).text() ).toBe( 'neowiki-infobox-type' );
		expect( statementElements[ 0 ].find( '.auto-infobox__value' ).text() ).toBe( 'TestSchema' );

		expect( statementElements[ 1 ].find( '.auto-infobox__property' ).text() ).toBe( 'name' );
		expect( statementElements[ 1 ].find( '.auto-infobox__value' ).text() ).toBe( 'John Doe, Jane Doe' );

		expect( statementElements[ 2 ].find( '.auto-infobox__property' ).text() ).toBe( 'age' );
		expect( statementElements[ 2 ].find( '.auto-infobox__value' ).text() ).toBe( '30' );

		expect( statementElements[ 3 ].find( '.auto-infobox__property' ).text() ).toBe( 'website' );
		const linkElement = statementElements[ 3 ].find( '.auto-infobox__value a' );
		expect( linkElement.attributes( 'href' ) ).toBe( 'https://example.com' );
		expect( linkElement.text() ).toBe( 'https://example.com' );
	} );

	it( 'renders without statements when subject has no statements', () => {
		const emptySubject = new Subject(
			new SubjectId( 's1demo6sssssss1' ),
			'Empty Subject',
			'TestSchema',
			new StatementList( [] ),
			new PageIdentifiers( 2, 'Empty_Subject' )
		);

		const wrapper = mountComponent( emptySubject, mockSchema, false );

		const statementElements = wrapper.findAll( '.auto-infobox__item' );
		expect( statementElements ).toHaveLength( 1 ); // Only schema badge
	} );

	it( 'does not render edit button when canEditSubject is false', () => {
		const wrapper = mountComponent( mockSubject, mockSchema, false );

		expect( wrapper.find( '.auto-infobox__footer' ).exists() ).toBe( false );
	} );

	it( 'renders edit button when canEditSubject is true', () => {
		const wrapper = mountComponent( mockSubject, mockSchema, true );

		const editButton = wrapper.find( '.auto-infobox__footer button' );
		expect( editButton.exists() ).toBe( true );
		expect( editButton.text() ).toBe( 'neowiki-infobox-edit-link' );
	} );
} );
