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
import { CdxTable } from '@wikimedia/codex';
import type { TableColumn } from '@wikimedia/codex';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
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
				CdxTable: {
					template: '<div><slot name="header"></slot><slot></slot></div>',
					props: [ 'columns', 'data' ],
				},
				CdxIcon: true,
				CdxButton: true,
				CdxInfoChip: true,
				SchemaDisplayHeader: true,
			},
		},
	} );
}

describe( 'SchemaDisplay', () => {
	it( 'renders the header component with correct schema', () => {
		const schema = newSchema( {
			title: 'Test schema',
		} );

		const wrapper = mountComponent( schema );
		const header = wrapper.findComponent( SchemaDisplayHeader );

		expect( header.exists() ).toBe( true );
		expect( header.props( 'schema' ) ).toStrictEqual( schema );
	} );

	it( 'passes correct columns and data to CdxTable when properties exist', () => {
		const schema = newSchema( {
			title: 'Test schema',
			properties: new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Test property 1', {
					type: TextType.typeName,
					required: true,
					description: 'Description for test property 1',
				} ),
				createPropertyDefinitionFromJson( 'Test property 2', {
					type: NumberType.typeName,
					required: false,
					description: 'Description for test property 2',
				} ),
			] ),
		} );

		const wrapper = mountComponent( schema );
		const table = wrapper.findComponent( CdxTable );

		const data = table.props( 'data' ) as PropertyDefinition[];
		expect( data ).toHaveLength( 2 );
		expect( data[ 0 ].name.toString() ).toBe( 'Test property 1' );
		expect( data[ 0 ].type ).toBe( TextType.typeName );
		expect( data[ 1 ].name.toString() ).toBe( 'Test property 2' );
		expect( data[ 1 ].type ).toBe( NumberType.typeName );

		const columns = table.props( 'columns' ) as TableColumn[];
		expect( columns ).toHaveLength( 5 );
		expect( columns.map( ( c ) => c.id ) ).toEqual( [ 'name', 'type', 'required', 'default', 'description' ] );
	} );

	it( 'passes empty columns to CdxTable when no properties exist', () => {
		const schema = newSchema( {
			title: 'Empty schema',
			description: '',
		} );

		const wrapper = mountComponent( schema );
		const table = wrapper.findComponent( CdxTable );

		expect( table.props( 'data' ) ).toHaveLength( 0 );
		expect( table.props( 'columns' ) ).toHaveLength( 0 );
	} );
} );
