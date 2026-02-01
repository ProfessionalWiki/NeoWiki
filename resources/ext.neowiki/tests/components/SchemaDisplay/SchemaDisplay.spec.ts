import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import SchemaDisplay from '@/components/SchemaDisplay/SchemaDisplay.vue';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { NumberType } from '@/domain/propertyTypes/Number.ts';
import { UrlType } from '@/domain/propertyTypes/Url.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { setupMwMock } from '../../VueTestHelpers.ts';

const $i18n = vi.fn().mockImplementation( ( key ) => ( {
	text: () => key,
} ) );

function mountComponent( schema: Schema ): VueWrapper {
	setupMwMock( { functions: [ 'msg' ] } );

	return mount( SchemaDisplay, {
		props: { schema },
		global: {
			mocks: { $i18n },
			provide: {
				[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
			},
		},
	} );
}

describe( 'SchemaDisplay', () => {
	it( 'renders schema description', () => {
		const schema = new Schema(
			'Person',
			'A schema for people',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'name', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = mountComponent( schema );

		expect( wrapper.find( '.ext-neowiki-schema-display__description' ).text() ).toBe( 'A schema for people' );
	} );

	it( 'hides description when schema has no description', () => {
		const schema = new Schema(
			'Person',
			'',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'name', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = mountComponent( schema );

		expect( wrapper.find( '.ext-neowiki-schema-display__description' ).exists() ).toBe( false );
	} );

	it( 'renders property rows with correct name, type label, required status, and description', () => {
		const schema = new Schema(
			'Person',
			'',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'name', {
					type: TextType.typeName,
					required: true,
					description: 'Full name',
				} ),
				createPropertyDefinitionFromJson( 'age', {
					type: NumberType.typeName,
					required: false,
					description: 'Age in years',
				} ),
				createPropertyDefinitionFromJson( 'website', {
					type: UrlType.typeName,
					required: false,
					description: '',
				} ),
			] ),
		);

		const wrapper = mountComponent( schema );

		const rows = wrapper.findAll( '.ext-neowiki-schema-display__table tbody tr' );
		expect( rows ).toHaveLength( 3 );

		expect( rows[ 0 ].findAll( 'td' )[ 0 ].text() ).toBe( 'name' );
		expect( rows[ 0 ].findAll( 'td' )[ 1 ].text() ).toContain( 'neowiki-property-type-text' );
		expect( rows[ 0 ].findAll( 'td' )[ 2 ].text() ).toBe( 'neowiki-schema-display-required-yes' );
		expect( rows[ 0 ].findAll( 'td' )[ 3 ].text() ).toBe( 'Full name' );

		expect( rows[ 1 ].findAll( 'td' )[ 0 ].text() ).toBe( 'age' );
		expect( rows[ 1 ].findAll( 'td' )[ 1 ].text() ).toContain( 'neowiki-property-type-number' );
		expect( rows[ 1 ].findAll( 'td' )[ 2 ].text() ).toBe( 'neowiki-schema-display-required-no' );
		expect( rows[ 1 ].findAll( 'td' )[ 3 ].text() ).toBe( 'Age in years' );

		expect( rows[ 2 ].findAll( 'td' )[ 0 ].text() ).toBe( 'website' );
		expect( rows[ 2 ].findAll( 'td' )[ 1 ].text() ).toContain( 'neowiki-property-type-url' );
		expect( rows[ 2 ].findAll( 'td' )[ 2 ].text() ).toBe( 'neowiki-schema-display-required-no' );
		expect( rows[ 2 ].findAll( 'td' )[ 3 ].text() ).toBe( '' );
	} );

	it( 'shows empty message when schema has no properties', () => {
		const schema = new Schema(
			'Empty',
			'',
			new PropertyDefinitionList( [] ),
		);

		const wrapper = mountComponent( schema );

		expect( wrapper.find( '.ext-neowiki-schema-display__table' ).exists() ).toBe( false );
		expect( wrapper.find( '.ext-neowiki-schema-display__empty' ).text() ).toBe( 'neowiki-schema-display-no-properties' );
	} );
} );
