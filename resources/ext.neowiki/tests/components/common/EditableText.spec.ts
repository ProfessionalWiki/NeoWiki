import { mount, VueWrapper, DOMWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
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
	describe( 'multiline', () => {
		const mountMultiline = ( props: Partial<InstanceType<typeof EditableText>['$props']> = {} ): VueWrapper =>
			mountComponent( { multiline: true, ...props } );

		async function startEditingMultiline( wrapper: VueWrapper ): Promise<DOMWrapper<Element>> {
			await editButton( wrapper ).trigger( 'click' );
			return wrapper.find( 'textarea' );
		}

		it( 'edits in a text area instead of a single-line input', async () => {
			const wrapper = mountMultiline();

			await editButton( wrapper ).trigger( 'click' );

			expect( wrapper.find( 'textarea' ).exists() ).toBe( true );
			expect( wrapper.find( 'input' ).exists() ).toBe( false );
		} );

		it( 'leaves Enter to insert a newline instead of committing', async () => {
			const wrapper = mountMultiline();

			const textarea = await startEditingMultiline( wrapper );
			await textarea.setValue( 'Two\nlines' );
			await textarea.trigger( 'keydown.enter' );

			expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
			expect( wrapper.find( 'textarea' ).exists() ).toBe( true );
		} );

		it( 'commits on Ctrl+Enter', async () => {
			const wrapper = mountMultiline();

			const textarea = await startEditingMultiline( wrapper );
			await textarea.setValue( 'Rewritten' );
			await textarea.trigger( 'keydown.enter', { ctrlKey: true } );

			expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ 'Rewritten' ] ] );
		} );

		it( 'commits on Cmd+Enter', async () => {
			const wrapper = mountMultiline();

			const textarea = await startEditingMultiline( wrapper );
			await textarea.setValue( 'Rewritten' );
			await textarea.trigger( 'keydown.enter', { metaKey: true } );

			expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ 'Rewritten' ] ] );
		} );

		it( 'still commits on blur', async () => {
			const wrapper = mountMultiline();

			const textarea = await startEditingMultiline( wrapper );
			await textarea.setValue( 'Rewritten' );
			await textarea.trigger( 'blur' );

			expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ 'Rewritten' ] ] );
		} );

		it( 'still discards the draft on Escape', async () => {
			const wrapper = mountMultiline();

			const textarea = await startEditingMultiline( wrapper );
			await textarea.setValue( 'Rewritten' );
			await textarea.trigger( 'keyup.esc' );

			expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
			expect( wrapper.find( 'textarea' ).exists() ).toBe( false );
		} );
	} );

	describe( 'clamping', () => {
		const LONG_TEXT = 'A description long enough to need more than the two lines it is given.';

		// jsdom lays nothing out, so the rendered text height and the line height
		// the clamp is derived from are both supplied here.
		const realGetComputedStyle = window.getComputedStyle.bind( window );

		function stubMeasuredHeights( textHeight: number, lineHeight: number ): void {
			Range.prototype.getBoundingClientRect = () => ( { height: textHeight } ) as DOMRect;

			vi.spyOn( window, 'getComputedStyle' ).mockImplementation( ( element: Element ) =>
				element.classList.contains( 'ext-neowiki-editable-text__text' ) ?
					( { lineHeight: `${ lineHeight }px`, fontSize: '14px' } as CSSStyleDeclaration ) :
					realGetComputedStyle( element ),
			);
		}

		async function mountClamped(
			props: Partial<InstanceType<typeof EditableText>['$props']> = {},
		): Promise<VueWrapper> {
			const wrapper = mountComponent( {
				modelValue: LONG_TEXT,
				clampLines: 2,
				expandLabel: 'Show all',
				collapseLabel: 'Show less',
				...props,
			} );
			await flushPromises();
			return wrapper;
		}

		function revealButton( wrapper: VueWrapper ): DOMWrapper<Element> {
			return wrapper.find( 'button[aria-label="Show all"]' );
		}

		afterEach( () => {
			delete ( Range.prototype as Partial<Range> ).getBoundingClientRect;
			vi.restoreAllMocks();
		} );

		it( 'limits the height of the text while collapsed', async () => {
			stubMeasuredHeights( 20, 20 );

			const wrapper = await mountClamped();

			expect( wrapper.find( '.ext-neowiki-editable-text__text' ).attributes( 'style' ) )
				.toContain( 'max-height' );
		} );

		it( 'offers no reveal while the text fits', async () => {
			stubMeasuredHeights( 40, 20 );

			const wrapper = await mountClamped();

			expect( revealButton( wrapper ).exists() ).toBe( false );
		} );

		it( 'offers a reveal once the text overflows', async () => {
			stubMeasuredHeights( 80, 20 );

			const wrapper = await mountClamped();

			expect( revealButton( wrapper ).exists() ).toBe( true );
		} );

		it( 'lifts the controls onto the last line only while the text is cut off', async () => {
			stubMeasuredHeights( 80, 20 );

			const wrapper = await mountClamped();

			expect( wrapper.classes() ).toContain( 'ext-neowiki-editable-text--clamped' );
		} );

		it( 'drops the clamp and the lift when the reveal is used', async () => {
			stubMeasuredHeights( 80, 20 );
			const wrapper = await mountClamped();

			await revealButton( wrapper ).trigger( 'click' );

			expect( wrapper.find( '.ext-neowiki-editable-text__text' ).attributes( 'style' ) ?? '' )
				.not.toContain( 'max-height' );
			expect( wrapper.classes() ).not.toContain( 'ext-neowiki-editable-text--clamped' );
		} );

		it( 'labels the reveal for collapsing once expanded', async () => {
			stubMeasuredHeights( 80, 20 );
			const wrapper = await mountClamped();

			await revealButton( wrapper ).trigger( 'click' );

			expect( wrapper.find( 'button[aria-label="Show less"]' ).exists() ).toBe( true );
		} );

		it( 'collapses a replaced value that the reader had expanded', async () => {
			stubMeasuredHeights( 80, 20 );
			const wrapper = await mountClamped();
			await revealButton( wrapper ).trigger( 'click' );

			await wrapper.setProps( { modelValue: 'A different description entirely.' } );
			await flushPromises();

			expect( wrapper.find( '.ext-neowiki-editable-text__text' ).attributes( 'style' ) )
				.toContain( 'max-height' );
		} );

		it( 'edits through the compact button rather than the full-size one', async () => {
			stubMeasuredHeights( 80, 20 );

			const wrapper = await mountClamped();

			expect( wrapper.find( '.ext-neowiki-editable-text__icon-button[aria-label="Rename"]' ).exists() ).toBe( true );
			expect( wrapper.find( '.ext-neowiki-editable-text__edit-button' ).exists() ).toBe( false );
		} );
	} );
	describe( 'the empty state', () => {
		const addProps = { modelValue: '', addLabel: 'Add description', placeholder: 'Unnamed' };

		function addButton( wrapper: VueWrapper ): DOMWrapper<Element> {
			return wrapper.find( '.ext-neowiki-editable-text__add-button' );
		}

		it( 'invites the value to be written instead of showing a placeholder and a pencil', () => {
			const wrapper = mountComponent( addProps );

			expect( addButton( wrapper ).text() ).toBe( 'Add description' );
			expect( wrapper.find( '.ext-neowiki-editable-text__text' ).exists() ).toBe( false );
			expect( editButton( wrapper ).exists() ).toBe( false );
		} );

		it( 'starts editing when it is pressed', async () => {
			const wrapper = mountComponent( addProps );

			await addButton( wrapper ).trigger( 'click' );

			expect( wrapper.find( 'input' ).exists() ).toBe( true );
		} );

		it( 'gives way to the value once one is written', async () => {
			const wrapper = mountComponent( addProps );

			await wrapper.setProps( { modelValue: 'Written at last' } );

			expect( addButton( wrapper ).exists() ).toBe( false );
			expect( wrapper.find( '.ext-neowiki-editable-text__text' ).text() ).toBe( 'Written at last' );
		} );

		it( 'keeps the placeholder for a host that asks for no invitation', () => {
			const wrapper = mountComponent( { modelValue: '', placeholder: 'Unnamed' } );

			expect( addButton( wrapper ).exists() ).toBe( false );
			expect( wrapper.find( '.ext-neowiki-editable-text__text' ).text() ).toBe( 'Unnamed' );
		} );
	} );
} );
