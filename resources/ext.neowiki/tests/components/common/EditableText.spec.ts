import { mount, VueWrapper, DOMWrapper } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import EditableText from '@/components/common/EditableText.vue';

describe( 'EditableText', () => {
	const mountComponent = ( props: Partial<InstanceType<typeof EditableText>['$props']> = {} ): VueWrapper =>
		mount( EditableText, {
			props: {
				modelValue: 'Acme Anvil',
				editButtonLabel: 'Rename',
				inputAriaLabel: 'Subject label',
				...props,
			},
		} );

	function editButton( wrapper: VueWrapper ): DOMWrapper<Element> {
		return wrapper.find( 'button[aria-label="Rename"]' );
	}

	async function startEditing( wrapper: VueWrapper ): Promise<DOMWrapper<Element>> {
		await editButton( wrapper ).trigger( 'click' );
		return wrapper.find( 'input' );
	}

	it( 'shows the value as text', () => {
		const wrapper = mountComponent();

		expect( wrapper.find( '.ext-neowiki-editable-text__text' ).text() ).toBe( 'Acme Anvil' );
	} );

	it( 'shows the placeholder when the value is empty', () => {
		const wrapper = mountComponent( { modelValue: '', placeholder: 'Unnamed' } );

		expect( wrapper.find( '.ext-neowiki-editable-text__text' ).text() ).toBe( 'Unnamed' );
	} );

	it( 'opens an input holding the current value when the edit button is clicked', async () => {
		const wrapper = mountComponent();

		const input = await startEditing( wrapper );

		expect( ( input.element as HTMLInputElement ).value ).toBe( 'Acme Anvil' );
	} );

	it( 'commits the draft on Enter', async () => {
		const wrapper = mountComponent();

		const input = await startEditing( wrapper );
		await input.setValue( 'Renamed Anvil' );
		await input.trigger( 'keydown.enter' );

		expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ 'Renamed Anvil' ] ] );
	} );

	it( 'commits the draft on blur', async () => {
		const wrapper = mountComponent();

		const input = await startEditing( wrapper );
		await input.setValue( 'Renamed Anvil' );
		await input.trigger( 'blur' );

		expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ 'Renamed Anvil' ] ] );
	} );

	it( 'returns to display mode after committing', async () => {
		const wrapper = mountComponent();

		const input = await startEditing( wrapper );
		await input.trigger( 'keydown.enter' );

		expect( wrapper.find( 'input' ).exists() ).toBe( false );
		expect( wrapper.find( '.ext-neowiki-editable-text__text' ).exists() ).toBe( true );
	} );

	it( 'does not emit when the draft equals the value', async () => {
		const wrapper = mountComponent();

		const input = await startEditing( wrapper );
		await input.trigger( 'keydown.enter' );

		expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
	} );

	it( 'discards the draft on Escape', async () => {
		const wrapper = mountComponent();

		const input = await startEditing( wrapper );
		await input.setValue( 'Renamed Anvil' );
		await input.trigger( 'keyup.esc' );

		expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
		expect( wrapper.find( 'input' ).exists() ).toBe( false );
	} );

	it( 'does not commit the blur that follows an Escape', async () => {
		const wrapper = mountComponent();

		const input = await startEditing( wrapper );
		await input.setValue( 'Renamed Anvil' );
		await input.trigger( 'keyup.esc' );
		await input.trigger( 'blur' );

		expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
	} );

	it( 'stops the Escape keyup from reaching ancestors, so a host dialog stays open', async () => {
		const escapeReachedAncestor = vi.fn();
		document.body.addEventListener( 'keyup', escapeReachedAncestor );
		const wrapper = mount( EditableText, {
			attachTo: document.body,
			props: {
				modelValue: 'Acme Anvil',
				editButtonLabel: 'Rename',
				inputAriaLabel: 'Subject label',
			},
		} );

		const input = await startEditing( wrapper );
		await input.trigger( 'keyup.esc' );

		expect( escapeReachedAncestor ).not.toHaveBeenCalled();
		document.body.removeEventListener( 'keyup', escapeReachedAncestor );
		wrapper.unmount();
	} );

	it( 'ignores an Enter that confirms text composition', async () => {
		const wrapper = mountComponent();

		const input = await startEditing( wrapper );
		await input.setValue( 'Renamed Anvil' );
		await input.trigger( 'keydown.enter', { isComposing: true } );

		expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
		expect( wrapper.find( 'input' ).exists() ).toBe( true );
	} );

	it( 'ignores an Escape that cancels text composition', async () => {
		const wrapper = mountComponent();

		const input = await startEditing( wrapper );
		await input.setValue( 'Renamed Anvil' );
		await input.trigger( 'keyup.esc', { isComposing: true } );

		expect( wrapper.find( 'input' ).exists() ).toBe( true );
	} );

	it( 'aborts editing when the value changes underneath the edit', async () => {
		const wrapper = mountComponent();

		await startEditing( wrapper );
		await wrapper.setProps( { modelValue: 'Replaced Subject' } );

		expect( wrapper.find( 'input' ).exists() ).toBe( false );
		expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
	} );

	it( 'keeps the input open when a required value is committed blank', async () => {
		const wrapper = mountComponent( { required: true } );

		const input = await startEditing( wrapper );
		await input.setValue( '   ' );
		await input.trigger( 'keydown.enter' );

		expect( wrapper.find( 'input' ).exists() ).toBe( true );
	} );

	it( 'emits the blank draft while staying open, so the host can flag it', async () => {
		const wrapper = mountComponent( { required: true } );

		const input = await startEditing( wrapper );
		await input.setValue( '' );
		await input.trigger( 'keydown.enter' );

		expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ '' ] ] );
	} );

	it( 'closes on a blank commit when the value is not required', async () => {
		const wrapper = mountComponent();

		const input = await startEditing( wrapper );
		await input.setValue( '' );
		await input.trigger( 'keydown.enter' );

		expect( wrapper.find( 'input' ).exists() ).toBe( false );
	} );

	it( 'still discards a blank draft on Escape when required', async () => {
		const wrapper = mountComponent( { required: true } );

		const input = await startEditing( wrapper );
		await input.setValue( '' );
		await input.trigger( 'keyup.esc' );

		expect( wrapper.find( 'input' ).exists() ).toBe( false );
		expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
	} );

	it( 'marks the input with the given status', async () => {
		const wrapper = mountComponent( { status: 'error' } );

		await startEditing( wrapper );

		expect( wrapper.find( '.cdx-text-input' ).classes() ).toContain( 'cdx-text-input--status-error' );
	} );

	it( 'labels the input for assistive technology', async () => {
		const wrapper = mountComponent();

		const input = await startEditing( wrapper );

		expect( input.attributes( 'aria-label' ) ).toBe( 'Subject label' );
	} );

	it( 'marks the input invalid for assistive technology when in error', async () => {
		const wrapper = mountComponent( { status: 'error' } );

		const input = await startEditing( wrapper );

		expect( input.attributes( 'aria-invalid' ) ).toBe( 'true' );
	} );

	describe( 'focus management', () => {
		function mountAttached(): VueWrapper {
			return mount( EditableText, {
				attachTo: document.body,
				props: {
					modelValue: 'Acme Anvil',
					editButtonLabel: 'Rename',
					inputAriaLabel: 'Subject label',
				},
			} );
		}

		it( 'focuses the input when editing starts', async () => {
			const wrapper = mountAttached();

			const input = await startEditing( wrapper );

			expect( document.activeElement ).toBe( input.element );
			wrapper.unmount();
		} );

		it( 'returns focus to the edit button after committing with Enter', async () => {
			const wrapper = mountAttached();

			const input = await startEditing( wrapper );
			await input.setValue( 'Renamed Anvil' );
			await input.trigger( 'keydown.enter' );
			await nextTick();

			expect( document.activeElement ).toBe( editButton( wrapper ).element );
			wrapper.unmount();
		} );

		it( 'returns focus to the edit button after cancelling with Escape', async () => {
			const wrapper = mountAttached();

			const input = await startEditing( wrapper );
			await input.trigger( 'keyup.esc' );
			await nextTick();

			expect( document.activeElement ).toBe( editButton( wrapper ).element );
			wrapper.unmount();
		} );
	} );
} );
