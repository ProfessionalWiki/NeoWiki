import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxSelect } from '@wikimedia/codex';
import PropertyDefinitionEditor from '@/components/SchemaEditor/PropertyDefinitionEditor.vue';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { SelectProperty } from '@/domain/propertyTypes/Select';
import { PropertyDefinition } from '@/domain/PropertyDefinition';
import { newStringValue } from '@/domain/Value';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers.ts';

describe( 'PropertyDefinitionEditor', () => {
	beforeEach( () => {
		setupMwMock();
	} );

	function newWrapper( property: PropertyDefinition ): VueWrapper {
		return createTestWrapper( PropertyDefinitionEditor, { property } );
	}

	function lastEmittedProperty( wrapper: VueWrapper ): PropertyDefinition {
		const emitted = wrapper.emitted( 'update:property-definition' );
		return emitted![ emitted!.length - 1 ][ 0 ] as PropertyDefinition;
	}

	async function changeTypeTo( wrapper: VueWrapper, type: string ): Promise<void> {
		await wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', type );
	}

	it( 'initializes the type-specific fields when the type changes to Select', async () => {
		const wrapper = newWrapper( newTextProperty( { name: 'Status' } ) );

		await changeTypeTo( wrapper, 'select' );

		const property = lastEmittedProperty( wrapper ) as SelectProperty;
		expect( property.type ).toBe( 'select' );
		expect( property.options ).toEqual( [] );
		expect( property.multiple ).toBe( false );
	} );

	it( 'preserves the shared fields when the type changes', async () => {
		const wrapper = newWrapper( newTextProperty( { name: 'Status', required: true } ) );

		await changeTypeTo( wrapper, 'select' );

		const property = lastEmittedProperty( wrapper );
		expect( property.name.toString() ).toBe( 'Status' );
		expect( property.required ).toBe( true );
	} );

	it( 'clears the now-invalid default value when the type changes', async () => {
		const wrapper = newWrapper( newTextProperty( { name: 'Status', default: newStringValue( 'draft' ) } ) );

		await changeTypeTo( wrapper, 'select' );

		expect( lastEmittedProperty( wrapper ).default ).toBeUndefined();
	} );
} );
