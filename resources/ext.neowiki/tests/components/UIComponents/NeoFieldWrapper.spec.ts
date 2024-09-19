import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import NeoFieldWrapper from '@/components/UIComponents/NeoFieldWrapper.vue';
import { CdxField } from '@wikimedia/codex';

describe( 'NeoFieldWrapper', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	it( 'renders correctly with slots', () => {
		const wrapper = mount( NeoFieldWrapper, {
			props: {
				required: true,
				minLength: 2,
				maxLength: 50,
				inputType: 'text'
			},
			slots: {
				label: 'Test Label',
				default: `
          <template #default="{ id, validateInput }">
            <input
              :id="id"
              @input="validateInput($event.target.value)"
            >
          </template>
        `
			}
		} );

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.find( 'input' ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'passes props correctly to CdxField', () => {
		const wrapper = mount( NeoFieldWrapper, {
			props: {
				required: true,
				minLength: 2,
				maxLength: 50,
				inputType: 'text'
			},
			slots: {
				label: 'Test Label',
				default: `
          <template #default="{ id, validateInput }">
            <input
              :id="id"
              @input="validateInput($event.target.value)"
            >
          </template>
        `
			}
		} );

		const cdxField = wrapper.findComponent( CdxField );
		expect( cdxField.attributes( 'required' ) ).toBeDefined();
		expect( cdxField.props( 'status' ) ).toBe( 'default' );
	} );

	it( 'validates required field', async () => {
		const wrapper = mount( NeoFieldWrapper, {
			props: {
				required: true,
				inputType: 'text'
			},
			slots: {
				label: 'Test Label',
				default: `
          <template #default="{ id, validateInput }">
            <input
              :id="id"
              @input="validateInput($event.target.value)"
            >
          </template>
        `
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( '' );
		await input.trigger( 'input' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-required' );
	} );

	it( 'validates text input length', async () => {
		const wrapper = mount( NeoFieldWrapper, {
			props: {
				minLength: 5,
				maxLength: 10,
				inputType: 'text'
			},
			slots: {
				label: 'Test Label',
				default: `
          <template #default="{ id, validateInput }">
            <input
              :id="id"
              @input="validateInput($event.target.value)"
            >
          </template>
        `
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 'abc' );
		await input.trigger( 'input' );
		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-min-length' );

		await input.setValue( 'abcdefghijklm' );
		await input.trigger( 'input' );
		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-max-length' );
	} );

	it( 'emits valid field', async () => {
		const wrapper = mount( NeoFieldWrapper, {
			props: {
				required: true,
				inputType: 'text'
			},
			slots: {
				label: 'Test Label',
				default: `
          <template #default="{ id, validateInput }">
            <input
              :id="id"
              @input="validateInput($event.target.value)"
            >
          </template>
        `
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 'valid' );
		await input.trigger( 'input' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ true ] );
	} );

	it( 'emits invalid field', async () => {
		const wrapper = mount( NeoFieldWrapper, {
			props: {
				required: true,
				inputType: 'url'
			},
			slots: {
				label: 'Test Label',
				default: `
          <template #default="{ id, validateInput }">
            <input
              :id="id"
              @input="validateInput($event.target.value)"
            >
          </template>
        `
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 'invalidurl' );
		await input.trigger( 'input' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ false ] );
	} );

} );
