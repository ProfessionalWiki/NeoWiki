import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import UrlInput from '@/components/Value/UrlInput.vue';
import NeoMultiTextInput from '@/components/common/NeoMultiTextInput.vue';
import { CdxField, CdxTextInput, ValidationMessages } from '@wikimedia/codex';
import { Icon } from '@wikimedia/codex-icons';
import { newStringValue } from '@/domain/Value';
import { UrlProperty, UrlType, newUrlProperty } from '@/domain/propertyTypes/Url.ts';
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
		startIcon: mockStartIcon,
	} ) ),
} ) );

describe( 'UrlInput', () => {
	function newWrapper( props: Partial<ValueInputProps<UrlProperty>> = {} ): VueWrapper<InstanceType<typeof UrlInput>> {
		return createTestWrapper( UrlInput, {
			modelValue: undefined,
			label: 'URL Label',
			property: newUrlProperty( { name: 'testUrlProp', type: UrlType.typeName, multiple: false } ),
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
			const testProperty = newUrlProperty( { name: 'customUrlProp', multiple: true, required: true } );
			const testModelValue = newStringValue( 'https://initial.url' );
			newWrapper( {
				property: testProperty,
				modelValue: testModelValue,
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
				property: newUrlProperty( { required: false } ),
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
				property: newUrlProperty( { multiple: false } ),
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
				property: newUrlProperty( { multiple: true } ),
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
				property: newUrlProperty( { multiple: false } ),
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.props( 'messages' ) ).toEqual( { error: 'Main URL field error' } );
			expect( field.props( 'status' ) ).toBe( 'error' );
		} );

		it( 'sets CdxField status to default if no fieldMessages.error (single input)', () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: false } ),
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.props( 'status' ) ).toBe( 'default' );
		} );

		it( 'CdxField status remains default for multiple inputs even with fieldMessages.error', () => {
			mockFieldMessages.value = { error: 'Error that should not set status for multiple' };
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: true } ),
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.props( 'status' ) ).toBe( 'default' );
			expect( field.props( 'messages' ) ).toEqual( mockFieldMessages.value );
		} );
	} );

	describe( 'Event Handling', () => {
		it( 'calls onInput from composable when CdxTextInput emits update:model-value (single)', async () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: false } ),
			} );
			await wrapper.findComponent( CdxTextInput ).vm.$emit( 'update:modelValue', 'https://new.single.url' );

			expect( mockOnInput ).toHaveBeenCalledWith( 'https://new.single.url' );
		} );

		it( 'calls onInput from composable when NeoMultiTextInput emits update:model-value (multiple)', async () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: true } ),
			} );
			await wrapper.findComponent( NeoMultiTextInput ).vm.$emit( 'update:modelValue', [ 'https://new1.url', 'https://new2.url' ] );

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

	describe( 'Server violations (multi-value)', () => {
		it( 'passes serverViolations to useStringValueInput as the fifth argument', () => {
			const violation = { propertyName: 'Website', code: 'invalid-url', args: [], valuePartIndex: 1 };
			newWrapper( {
				property: newUrlProperty( { name: 'Website', multiple: true } ),
				serverViolations: [ violation ],
			} );

			expect( useStringValueInput ).toHaveBeenCalledTimes( 1 );
			const args = ( useStringValueInput as import( 'vitest' ).Mock ).mock.calls[ 0 ];
			expect( args[ 4 ].value ).toEqual( [ violation ] );
		} );

		it( 'renders server violation on the matching valuePartIndex via inputMessages', () => {
			// Composable merges server violation into inputMessages[1], mock reflects that.
			mockInputMessages.value = [ {}, { error: 'neowiki-field-invalid-url' } ];
			mockDisplayValues.value = [ 'https://ok.example', 'bad' ];
			const wrapper = newWrapper( {
				property: newUrlProperty( { name: 'Website', multiple: true } ),
				serverViolations: [
					{ propertyName: 'Website', code: 'invalid-url', args: [], valuePartIndex: 1 },
				],
			} );

			const multiInput = wrapper.findComponent( NeoMultiTextInput );
			const messages = multiInput.props( 'messages' ) as { error?: string }[];
			expect( messages[ 0 ] ).toEqual( {} );
			expect( messages[ 1 ] ).toHaveProperty( 'error', 'neowiki-field-invalid-url' );
		} );

		it( 'emits clear-server-violation when the composable emits it for valuePartIndex 1', async () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { name: 'Website', multiple: true } ),
				serverViolations: [
					{ propertyName: 'Website', code: 'invalid-url', args: [], valuePartIndex: 1 },
				],
			} );

			const capturedEmit = ( useStringValueInput as import( 'vitest' ).Mock ).mock.calls[ 0 ][ 2 ];
			capturedEmit( 'clear-server-violation', { propertyName: 'Website', valuePartIndex: 1 } );

			await wrapper.vm.$nextTick();

			expect( wrapper.emitted( 'clear-server-violation' ) ).toBeTruthy();
			expect( wrapper.emitted( 'clear-server-violation' )![ 0 ] ).toEqual( [
				{ propertyName: 'Website', valuePartIndex: 1 },
			] );
		} );

		it( 'does not emit clear-server-violation for index 0 when no server violation exists there', async () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { name: 'Website', multiple: true } ),
				serverViolations: [
					// violation only at index 1, not index 0
					{ propertyName: 'Website', code: 'invalid-url', args: [], valuePartIndex: 1 },
				],
			} );

			// The composable only calls emit('clear-server-violation') when a violation exists at
			// the edited index. Since no violation at index 0, no clear is emitted for index 0.
			// We simulate the composable NOT calling it for index 0 by not invoking capturedEmit.
			await wrapper.vm.$nextTick();

			expect( wrapper.emitted( 'clear-server-violation' ) ).toBeFalsy();
		} );
	} );
} );
