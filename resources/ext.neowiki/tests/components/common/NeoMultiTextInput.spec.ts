import { VueWrapper, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import NeoMultiTextInput from '@/components/common/NeoMultiTextInput.vue';
import { CdxTextInput, CdxMessage } from '@wikimedia/codex';

// Helper function to create the wrapper with default props
function createWrapper( props: Partial<InstanceType<typeof NeoMultiTextInput>['$props']> = {} ): VueWrapper {
	return mount( NeoMultiTextInput, {
		props: {
			label: 'Test Label', // Default label for aria-label generation
			modelValue: [ '' ], // Default to a single empty input
			...props,
		},
		global: {
			components: {
				CdxTextInput,
				CdxMessage,
			},
		},
	} );
}

describe( 'NeoMultiTextInput', () => {

	describe( 'rendering', () => {
		it( 'renders an input field for each value plus one empty trailing input', () => {
			const wrapper = createWrapper( { modelValue: [ 'value1', 'value2' ] } );
			// Expect 3 inputs: 'value1', 'value2', ''
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 3 );
			const inputs = wrapper.findAll( 'input' );
			expect( inputs[ 0 ].element.value ).toBe( 'value1' );
			expect( inputs[ 1 ].element.value ).toBe( 'value2' );
			expect( inputs[ 2 ].element.value ).toBe( '' );
		} );

		it( 'renders only one input if modelValue is empty', () => {
			const wrapper = createWrapper( { modelValue: [] } );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 1 );
			expect( wrapper.find( 'input' ).element.value ).toBe( '' );
		} );

		it( 'renders only one input if modelValue is just one empty string', () => {
			const wrapper = createWrapper( { modelValue: [ '' ] } );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 1 );
			expect( wrapper.find( 'input' ).element.value ).toBe( '' );
		} );

		it( 'renders two inputs if modelValue has one value', () => {
			const wrapper = createWrapper( { modelValue: [ 'value1' ] } );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 2 );
			const inputs = wrapper.findAll( 'input' );
			expect( inputs[ 0 ].element.value ).toBe( 'value1' );
			expect( inputs[ 1 ].element.value ).toBe( '' );
		} );

		it( 'assigns correct aria-label to each input', () => {
			const wrapper = createWrapper( { modelValue: [ 'one', 'two' ], label: 'My Items' } );
			const inputs = wrapper.findAll( 'input' );
			expect( inputs[ 0 ].attributes( 'aria-label' ) ).toBe( 'My Items item 1' );
			expect( inputs[ 1 ].attributes( 'aria-label' ) ).toBe( 'My Items item 2' );
			expect( inputs[ 2 ].attributes( 'aria-label' ) ).toBe( 'My Items item 3' );
		} );
	} );

	describe( 'value handling', () => {
		it( 'adds a new empty input when text is entered into the last input', async () => {
			const wrapper = createWrapper( { modelValue: [ 'value1' ] } );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 2 );

			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 1 ].vm.$emit( 'update:modelValue', 'new value' );

			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 3 );
			expect( wrapper.findAll( 'input' )[ 2 ].element.value ).toBe( '' );
		} );

		it( 'does not add another empty input if the last input remains empty', async () => {
			const wrapper = createWrapper( { modelValue: [ 'value1' ] } );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 2 );

			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 1 ].vm.$emit( 'update:modelValue', 'a' );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 3 );
			await inputs[ 1 ].vm.$emit( 'update:modelValue', '' );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 2 );
		} );

		it( 'removes the last empty input if the second-to-last input becomes empty', async () => {
			const wrapper = createWrapper( { modelValue: [ 'value1', 'value2' ] } );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 3 );

			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 1 ].vm.$emit( 'update:modelValue', '' );

			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 2 );
			const renderedInputs = wrapper.findAll( 'input' );
			expect( renderedInputs[ 0 ].element.value ).toBe( 'value1' );
			expect( renderedInputs[ 1 ].element.value ).toBe( '' );
		} );

		it( 'emits update:modelValue without the trailing empty string', async () => {
			const wrapper = createWrapper( { modelValue: [ 'value1' ] } );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 1 ].vm.$emit( 'update:modelValue', 'value2' );

			const emitted = wrapper.emitted( 'update:modelValue' );
			expect( emitted ).toBeTruthy();

			expect( emitted![ emitted!.length - 1 ][ 0 ] ).toEqual( [ 'value1', 'value2' ] );
		} );

		it( 'emits update:modelValue with only empty string if the only input is cleared', async () => {
			const wrapper = createWrapper( { modelValue: [ 'value1' ] } );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '' );

			const emitted = wrapper.emitted( 'update:modelValue' );
			expect( emitted ).toBeTruthy();
			expect( emitted![ emitted!.length - 1 ][ 0 ] ).toEqual( [ '' ] );
		} );

		it( 'correctly initializes and normalizes complex initial values', () => {
			const wrapper = createWrapper( { modelValue: [ 'a', '', 'b', '', '' ] } );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 3 );
			const inputs = wrapper.findAll( 'input' );
			expect( inputs[ 0 ].element.value ).toBe( 'a' );
			expect( inputs[ 1 ].element.value ).toBe( 'b' );
			expect( inputs[ 2 ].element.value ).toBe( '' );
		} );
	} );

	describe( 'message display', () => {
		it( 'does not display message initially', () => {
			const wrapper = createWrapper( {
				modelValue: [ 'value1' ],
				messages: [ { error: 'Error on first' } ],
			} );
			expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( false );
		} );

		it( 'displays message after input is blurred', async () => {
			const wrapper = createWrapper( {
				modelValue: [ 'value1' ],
				messages: [ { error: 'Error on first' } ],
			} );
			const textInput = wrapper.findComponent( CdxTextInput );

			await textInput.vm.$emit( 'blur' );

			const message = wrapper.findComponent( CdxMessage );
			expect( message.exists() ).toBe( true );
			expect( message.props( 'type' ) ).toBe( 'error' );
			expect( message.text() ).toContain( 'Error on first' );
		} );

		it( 'applies correct status to input based on message', async () => {
			const wrapper = createWrapper( {
				modelValue: [ 'value1', 'value2' ],
				messages: [ {}, { warning: 'Warning on second' } ],
			} );
			const textInputs = wrapper.findAllComponents( CdxTextInput );

			expect( textInputs[ 0 ].props( 'status' ) ).toBe( 'default' );
			expect( textInputs[ 1 ].props( 'status' ) ).toBe( 'warning' );

			await textInputs[ 1 ].vm.$emit( 'blur' );
			const message = wrapper.findComponent( CdxMessage );
			expect( message.exists() ).toBe( true );
			expect( message.props( 'type' ) ).toBe( 'warning' );
			expect( message.text() ).toContain( 'Warning on second' );
		} );

		it( 'does not display message if input has no message entry, even after blur', async () => {
			const wrapper = createWrapper( {
				modelValue: [ 'value1' ],
				messages: [],
			} );
			const textInput = wrapper.findComponent( CdxTextInput );
			await textInput.vm.$emit( 'blur' );
			expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( false );
		} );

		it( 'handles messages for the dynamically added input', async () => {
			const wrapper = createWrapper( {
				modelValue: [ 'v1' ],
				messages: [ {}, { success: 'Good job!' } ],
			} );
			const textInputs = wrapper.findAllComponents( CdxTextInput );

			await textInputs[ 1 ].vm.$emit( 'update:modelValue', 'v2' );
			await textInputs[ 1 ].vm.$emit( 'blur' );

			const messages = wrapper.findAllComponents( CdxMessage );
			expect( messages.length ).toBe( 1 );
			expect( messages[ 0 ].props( 'type' ) ).toBe( 'success' );
			expect( messages[ 0 ].text() ).toContain( 'Good job!' );

			const updatedInputs = wrapper.findAllComponents( CdxTextInput );
			expect( updatedInputs[ 1 ].props( 'status' ) ).toBe( 'success' );
		} );
	} );
} );
