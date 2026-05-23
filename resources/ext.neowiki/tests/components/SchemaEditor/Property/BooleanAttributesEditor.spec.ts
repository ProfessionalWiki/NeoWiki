import { VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import BooleanAttributesEditor from '@/components/SchemaEditor/Property/BooleanAttributesEditor.vue';
import { newBooleanProperty, BooleanProperty } from '@/domain/propertyTypes/Boolean';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper } from '../../../VueTestHelpers.ts';

describe( 'BooleanAttributesEditor', () => {
	function newWrapper( props: Partial<AttributesEditorProps<BooleanProperty>> = {} ): VueWrapper {
		return createTestWrapper( BooleanAttributesEditor, {
			property: newBooleanProperty(),
			...props,
		} );
	}

	it( 'mounts without error for a Boolean property', () => {
		const wrapper = newWrapper();

		expect( wrapper.exists() ).toBe( true );
	} );

	it( 'renders no type-specific input fields', () => {
		const wrapper = newWrapper();

		expect( wrapper.findAll( 'input' ).length ).toBe( 0 );
		expect( wrapper.findAll( 'select' ).length ).toBe( 0 );
		expect( wrapper.findAll( 'textarea' ).length ).toBe( 0 );
	} );

	it( 'does not emit update:property on mount', () => {
		const wrapper = newWrapper();

		expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
	} );
} );
