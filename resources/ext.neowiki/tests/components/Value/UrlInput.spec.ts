import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import UrlInput from '@/components/Value/UrlInput.vue';
import NeoMultiTextInput from '@/components/common/NeoMultiTextInput.vue';
import { CdxField, CdxTextInput, ValidationMessages } from '@wikimedia/codex';
import { Icon } from '@wikimedia/codex-icons';
import { newStringValue } from '@neo/domain/Value';
import { UrlProperty, UrlType, newUrlProperty } from '@neo/domain/propertyTypes/Url.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';
import { ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { useStringValueInput } from '@/composables/useStringValueInput.ts';

const mockOnInput = vi.fn();
const mockGetCurrentValue = vi.fn();
const mockDisplayValues = ref<string[]>( [] );
const mockFieldMessages = ref<ValidationMessages>( {} );
const mockInputMessages = ref<ValidationMessages[]>( [] );
const mockStartIcon = ref<Icon | undefined>( undefined );

// TODO: Should we move this into a mock file?
vi.mock( '@/composables/useStringValueInput.ts', () => ( {
	useStringValueInput: vi.fn( () => ( {
		displayValues: mockDisplayValues,
		fieldMessages: mockFieldMessages,
		inputMessages: mockInputMessages,
		onInput: mockOnInput,
		getCurrentValue: mockGetCurrentValue,
		startIcon: mockStartIcon
	} ) )
} ) );

describe( 'UrlInput', () => {
	function newWrapper( props: Partial<ValueInputProps<UrlProperty>> = {} ): VueWrapper<InstanceType<typeof UrlInput>> {
		return createTestWrapper( UrlInput, {
			modelValue: undefined,
			label: 'URL Label',
			property: newUrlProperty( { name: 'testUrlProp', type: UrlType.typeName, multiple: false } ),
			...props
		} );
	}

	beforeEach( () => {
		vi.clearAllMocks();
		mockDisplayValues.value = [];
		mockFieldMessages.value = {};
		mockInputMessages.value = [];
		mockStartIcon.value = undefined;
		mockGetCurrentValue.mockReturnValue( undefined );

		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	describe( 'Initialization and Prop Passing', () => {
		it( 'calls useStringValueInput with correct arguments', () => {
			const testProperty = newUrlProperty( { name: 'customUrlProp', multiple: true, required: true } );
			const testModelValue = newStringValue( 'https://initial.url' );
			newWrapper( {
				property: testProperty,
				modelValue: testModelValue
			} );

			expect( useStringValueInput ).toHaveBeenCalledTimes( 1 );
			const useStringValueInputArgs = ( useStringValueInput as import( 'vitest' ).Mock ).mock.calls[ 0 ];
			expect( useStringValueInputArgs[ 0 ].value ).toEqual( testModelValue );
			expect( useStringValueInputArgs[ 1 ].value ).toEqual( testProperty );
			expect( useStringValueInputArgs[ 3 ] ).toBeInstanceOf( UrlType );
		} );
	} );

	describe( 'Rendering based on props and composable state', () => {
		it( 'renders a CdxField with the label and optional status', () => {
			const wrapper = newWrapper( {
				label: 'My Awesome URL Label',
				property: newUrlProperty( { required: false } )
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.exists() ).toBe( true );
			expect( field.props( 'isFieldset' ) ).toBe( true );
			expect( wrapper.text() ).toContain( 'My Awesome URL Label' );
			expect( field.props( 'optional' ) ).toBe( true );
		} );

		it( 'renders CdxTextInput for single URL value', () => {
			mockDisplayValues.value = [ 'https://single.url' ];
			mockStartIcon.value = 'url-icon';
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: false } )
			} );

			expect( wrapper.findComponent( CdxTextInput ).exists() ).toBe( true );
			expect( wrapper.findComponent( NeoMultiTextInput ).exists() ).toBe( false );
			const textInput = wrapper.findComponent( CdxTextInput );
			expect( textInput.props( 'modelValue' ) ).toBe( 'https://single.url' );
			expect( textInput.props( 'startIcon' ) ).toBe( 'url-icon' );
		} );

		it( 'renders NeoMultiTextInput for multiple URL values', () => {
			mockDisplayValues.value = [ 'https://url1.com', 'https://url2.com' ];
			mockInputMessages.value = [ {}, { error: 'An error' } ];
			mockStartIcon.value = 'multi-url-icon';
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: true } )
			} );

			expect( wrapper.findComponent( NeoMultiTextInput ).exists() ).toBe( true );
			const multiInput = wrapper.findComponent( NeoMultiTextInput );
			expect( multiInput.props( 'modelValue' ) ).toEqual( [ 'https://url1.com', 'https://url2.com' ] );
			expect( multiInput.props( 'messages' ) ).toEqual( [ {}, { error: 'An error' } ] );
			expect( multiInput.props( 'startIcon' ) ).toBe( 'multi-url-icon' );
			expect( multiInput.props( 'label' ) ).toBe( 'URL Label' );
		} );

		it( 'passes fieldMessages to CdxField and sets status to error if fieldMessages.error exists (single input)', () => {
			mockFieldMessages.value = { error: 'Main URL field error' };
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: false } )
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.props( 'messages' ) ).toEqual( { error: 'Main URL field error' } );
			expect( field.props( 'status' ) ).toBe( 'error' );
		} );

		it( 'sets CdxField status to default if no fieldMessages.error (single input)', () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: false } )
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.props( 'status' ) ).toBe( 'default' );
		} );

		it( 'CdxField status remains default for multiple inputs even with fieldMessages.error', () => {
			mockFieldMessages.value = { error: 'Error that should not set status for multiple' };
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: true } )
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.props( 'status' ) ).toBe( 'default' );
			expect( field.props( 'messages' ) ).toEqual( mockFieldMessages.value );
		} );
	} );

	describe( 'Event Handling', () => {
		it( 'calls onInput from composable when CdxTextInput emits update:model-value (single)', async () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: false } )
			} );
			await wrapper.findComponent( CdxTextInput ).vm.$emit( 'update:model-value', 'https://new.single.url' );

			expect( mockOnInput ).toHaveBeenCalledWith( 'https://new.single.url' );
		} );

		it( 'calls onInput from composable when NeoMultiTextInput emits update:model-value (multiple)', async () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: true } )
			} );
			await wrapper.findComponent( NeoMultiTextInput ).vm.$emit( 'update:model-value', [ 'https://new1.url', 'https://new2.url' ] );

			expect( mockOnInput ).toHaveBeenCalledWith( [ 'https://new1.url', 'https://new2.url' ] );
		} );
	} );

	describe( 'Exposed Methods', () => {
		it( 'exposes getCurrentValue from composable', () => {
			const wrapper = newWrapper();
			mockGetCurrentValue.mockReturnValueOnce( newStringValue( 'https://exposed.url' ) );

			const exposedValue = ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue();
			expect( mockGetCurrentValue ).toHaveBeenCalledTimes( 1 );
			expect( exposedValue ).toEqual( newStringValue( 'https://exposed.url' ) );
		} );
	} );
} );
