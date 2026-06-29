import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxChipInput } from '@wikimedia/codex';
import SelectAttributesEditor from '@/components/SchemaEditor/Property/SelectAttributesEditor.vue';
import { newSelectProperty, SelectProperty } from '@/domain/propertyTypes/Select';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper, setupMwMock } from '../../../VueTestHelpers.ts';

describe( 'SelectAttributesEditor', () => {
	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-options-unique': 'Options must be unique.',
			},
			functions: [ 'message' ],
		} );
	} );

	function newWrapper( props: Partial<AttributesEditorProps<SelectProperty>> = {} ): VueWrapper {
		return createTestWrapper( SelectAttributesEditor, {
			property: newSelectProperty( {} ),
			...props,
		} );
	}

	it( 'displays existing options as chips', () => {
		const wrapper = newWrapper( {
			property: newSelectProperty( {
				options: [
					{ id: 'open', label: 'Open' },
					{ id: 'closed', label: 'Closed' },
				],
			} ),
		} );

		expect( wrapper.findComponent( CdxChipInput ).props( 'inputChips' ) ).toEqual( [
			{ value: 'Open' },
			{ value: 'Closed' },
		] );
	} );

	it( 'emits options when the chips change', async () => {
		const wrapper = newWrapper();

		await wrapper.findComponent( CdxChipInput ).vm.$emit( 'update:input-chips', [
			{ value: 'Draft' },
			{ value: 'Final' },
		] );

		expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ {
			options: [
				{ id: 'Draft', label: 'Draft' },
				{ id: 'Final', label: 'Final' },
			],
		} ] );
	} );

	it( 'emits multiple when the checkbox is toggled', async () => {
		const wrapper = newWrapper();

		await wrapper.find( 'input[type="checkbox"]' ).setValue( true );

		expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { multiple: true } ] );
	} );
} );
