import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SchemaDisplay from '@/components/SchemaDisplay/SchemaDisplay.vue';
import SchemaDisplayHeader from '@/components/SchemaDisplay/SchemaDisplayHeader.vue';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { NumberType } from '@/domain/propertyTypes/Number.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { setupMwMock, createI18nMock } from '../../VueTestHelpers.ts';
import { newSchema } from '@/TestHelpers.ts';

function mountComponent( schema: Schema ): VueWrapper {
	setupMwMock( { functions: [ 'msg' ] } );

	return mount( SchemaDisplay, {
		props: { schema },
		global: {
			mocks: { $i18n: createI18nMock() },
			provide: {
				[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
			},
			stubs: {
				CdxIcon: true,
				CdxInfoChip: { template: '<span><slot /></span>', props: [ 'icon' ] },
				SchemaDisplayHeader: true,
			},
		},
	} );
}

describe( 'SchemaDisplay', () => {
	it( 'passes schema to header component', () => {
		const schema = newSchema( { title: 'Test schema' } );

		const wrapper = mountComponent( schema );

		expect( wrapper.findComponent( SchemaDisplayHeader ).props( 'schema' ) ).toStrictEqual( schema );
	} );

	it( 'renders property names, types, and required status', () => {
		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Website', {
					type: TextType.typeName,
					required: true,
				} ),
				createPropertyDefinitionFromJson( 'Age', {
					type: NumberType.typeName,
					required: false,
				} ),
			] ),
		} );

		const wrapper = mountComponent( schema );
		const rows = wrapper.findAll( 'tbody tr' );

		expect( rows ).toHaveLength( 2 );
		expect( rows[ 0 ].text() ).toContain( 'Website' );
		expect( rows[ 0 ].text() ).toContain( 'neowiki-property-type-text' );
		expect( rows[ 0 ].text() ).toContain( 'neowiki-schema-display-required-yes' );
		expect( rows[ 1 ].text() ).toContain( 'Age' );
		expect( rows[ 1 ].text() ).toContain( 'neowiki-property-type-number' );
		expect( rows[ 1 ].text() ).toContain( 'neowiki-schema-display-required-no' );
	} );

	it( 'renders default values for properties that have them', () => {
		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'greeting', {
					type: TextType.typeName,
					default: [ 'Hello' ],
				} ),
			] ),
		} );

		const wrapper = mountComponent( schema );

		expect( wrapper.find( 'tbody tr' ).text() ).toContain( 'Hello' );
	} );

	it( 'shows empty message when schema has no properties', () => {
		const wrapper = mountComponent( newSchema( { properties: new PropertyDefinitionList( [] ) } ) );

		expect( wrapper.text() ).toContain( 'neowiki-schema-display-no-properties' );
		expect( wrapper.find( '.cdx-table__table__empty-state' ).exists() ).toBe( true );
	} );
} );
