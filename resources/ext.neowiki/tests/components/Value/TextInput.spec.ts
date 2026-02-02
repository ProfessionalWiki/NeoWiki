import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import TextInput from '@/components/Value/TextInput.vue';
import NeoMultiTextInput from '@/components/common/NeoMultiTextInput.vue';
import { CdxField, CdxIcon, CdxTextInput, ValidationMessages } from '@wikimedia/codex';
import { Icon } from '@wikimedia/codex-icons';
import { newStringValue } from '@/domain/Value';
import { TextProperty, newTextProperty, TextType } from '@/domain/propertyTypes/Text';
import { createTestWrapper } from '../../VueTestHelpers.ts';
import { ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { useStringValueInput } from '@/composables/useStringValueInput.ts';

const mockOnInput = vi.fn();
const mockGetCurrentValue = vi.fn();
const mockDisplayValues = ref<string[]>( [] );
const mockFieldMessages = ref<ValidationMessages>( {} );
const mockInputMessages = ref<ValidationMessages[]>( [] );
const mockStartIcon = ref<Icon | undefined>( undefined ); // Text inputs might not use startIcon

vi.mock( '@/composables/useStringValueInput.ts', () => ( {
	useStringValueInput: vi.fn( () => ( {
		displayValues: mockDisplayValues,
		fieldMessages: mockFieldMessages,
		inputMessages: mockInputMessages,
		onInput: mockOnInput,
		getCurrentValue: mockGetCurrentValue,
		startIcon: mockStartIcon,
	} ) ),
} ) );

describe( 'TextInput', () => {
	function newWrapper( props: Partial<ValueInputProps<TextProperty>> = {} ): VueWrapper<InstanceType<typeof TextInput>> {
		return createTestWrapper( TextInput, {
			modelValue: undefined, // Default to undefined, composable handles initial state
			label: 'Text Label',
			property: newTextProperty( { name: 'testTextProp', multiple: false } ),
			...props,
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
				parse: () => str,
			} ) ),
		} );
	} );

	describe( 'Initialization and Prop Passing', () => {
		it( 'calls useStringValueInput with correct arguments', () => {
			const testProperty = newTextProperty( { name: 'customTextProp', multiple: false, required: true } );
			const testModelValue = newStringValue( 'initial' );
			newWrapper( {
				label: 'My Custom Text Label',
				property: testProperty,
				modelValue: testModelValue,
			} );

			expect( useStringValueInput ).toHaveBeenCalledTimes( 1 );
			const useStringValueInputArgs = ( useStringValueInput as import( 'vitest' ).Mock ).mock.calls[ 0 ];
			expect( useStringValueInputArgs[ 0 ].value ).toEqual( testModelValue );
			expect( useStringValueInputArgs[ 1 ].value ).toEqual( testProperty );
			expect( useStringValueInputArgs[ 3 ] ).toBeInstanceOf( TextType );
		} );
	} );

	describe( 'Rendering based on props and composable state', () => {
		it( 'renders a CdxField with the label and optional status', () => {
			const wrapper = newWrapper( {
				label: 'My Awesome Text Label',
				property: newTextProperty( { required: false } ),
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.exists() ).toBe( true );
			expect( field.props( 'isFieldset' ) ).toBe( true );
			expect( wrapper.text() ).toContain( 'My Awesome Text Label' );
			expect( field.props( 'optional' ) ).toBe( true );
		} );

		it( 'renders CdxTextInput for single text value', () => {
			mockDisplayValues.value = [ 'Some text' ];
			// mockStartIcon.value = 'some-icon'; // If text inputs were to have icons
			const wrapper = newWrapper( {
				property: newTextProperty( { multiple: false } ),
			} );

			expect( wrapper.findComponent( CdxTextInput ).exists() ).toBe( true );
			expect( wrapper.findComponent( NeoMultiTextInput ).exists() ).toBe( false );
			const textInput = wrapper.findComponent( CdxTextInput );
			expect( textInput.props( 'modelValue' ) ).toBe( 'Some text' );
		} );

		it( 'renders NeoMultiTextInput for multiple text values', () => {
			mockDisplayValues.value = [ 'Text 1', 'Text 2' ];
			mockInputMessages.value = [ {}, { error: 'An error on Text 2' } ];
			const wrapper = newWrapper( {
				property: newTextProperty( { multiple: true } ),
			} );

			expect( wrapper.findComponent( NeoMultiTextInput ).exists() ).toBe( true );
			const multiInput = wrapper.findComponent( NeoMultiTextInput );
			expect( multiInput.props( 'modelValue' ) ).toEqual( [ 'Text 1', 'Text 2' ] );
			expect( multiInput.props( 'messages' ) ).toEqual( [ {}, { error: 'An error on Text 2' } ] );
			expect( multiInput.props( 'label' ) ).toBe( 'Text Label' ); // Passes down the main label
		} );

		it( 'passes fieldMessages to CdxField and sets status to error if fieldMessages.error exists (single input)', () => {
			mockFieldMessages.value = { error: 'Main text field error' };
			const wrapper = newWrapper( {
				property: newTextProperty( { multiple: false } ),
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.props( 'messages' ) ).toEqual( { error: 'Main text field error' } );
			expect( field.props( 'status' ) ).toBe( 'error' );
		} );

		it( 'sets CdxField status to default if no fieldMessages.error (single input)', () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { multiple: false } ),
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.props( 'status' ) ).toBe( 'default' );
		} );

		it( 'CdxField status remains default for multiple inputs even with fieldMessages.error', () => {
			mockFieldMessages.value = { error: 'Error that should not set status for multiple' };
			const wrapper = newWrapper( {
				property: newTextProperty( { multiple: true } ),
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.props( 'status' ) ).toBe( 'default' );
			expect( field.props( 'messages' ) ).toEqual( mockFieldMessages.value );
		} );
	} );

	describe( 'Description rendering', () => {
		it( 'renders info icon when property has a description', () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { description: 'Enter the full name' } ),
			} );

			expect( wrapper.findComponent( CdxIcon ).exists() ).toBe( true );
		} );

		it( 'does not render info icon when property has no description', () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { description: '' } ),
			} );

			expect( wrapper.findComponent( CdxIcon ).exists() ).toBe( false );
		} );
	} );

	describe( 'Event Handling', () => {
		it( 'calls onInput from composable when CdxTextInput emits update:model-value (single)', async () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { multiple: false } ),
			} );
			await wrapper.findComponent( CdxTextInput ).vm.$emit( 'update:model-value', 'new single text' );

			expect( mockOnInput ).toHaveBeenCalledWith( 'new single text' );
		} );

		it( 'calls onInput from composable when NeoMultiTextInput emits update:model-value (multiple)', async () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { multiple: true } ),
			} );
			await wrapper.findComponent( NeoMultiTextInput ).vm.$emit( 'update:model-value', [ 'new text 1', 'new text 2' ] );

			expect( mockOnInput ).toHaveBeenCalledWith( [ 'new text 1', 'new text 2' ] );
		} );
	} );

	describe( 'Exposed Methods', () => {
		it( 'exposes getCurrentValue from composable', () => {
			const wrapper = newWrapper();
			const expectedValue = newStringValue( 'exposed text value' );
			mockGetCurrentValue.mockReturnValueOnce( expectedValue );

			const exposedValue = ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue();
			expect( mockGetCurrentValue ).toHaveBeenCalledTimes( 1 );
			expect( exposedValue ).toEqual( expectedValue );
		} );
	} );
} );
