import { DOMWrapper, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { ref } from 'vue';
import SelectAttributesEditor from '@/components/SchemaEditor/Property/SelectAttributesEditor.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { ErrorsRevealedKey } from '@/components/SchemaEditor/ErrorsRevealed.ts';
import { newSelectProperty, SelectOption, SelectProperty } from '@/domain/propertyTypes/Select';
import { newStringValue } from '@/domain/Value.ts';
import { createI18nMock, setupMwMock } from '../../../VueTestHelpers.ts';

// Ids of every format a Schema can hold: the editor keeps whatever it was given.
const ACTIVE: SelectOption = { id: 'o1demo1aaaaaaa1', label: 'Active' };
const INACTIVE: SelectOption = { id: 'inactive', label: 'Inactive' };
const ACQUIRED: SelectOption = { id: 'https://example.org/status/acquired', label: 'Acquired' };

const ADD_OPTION = 'neowiki-property-editor-add-option';

// Mounted with the real Codex components, and attached, because where the focus goes is part of
// what is asserted.
describe( 'SelectAttributesEditor', () => {

	let wrapper: VueWrapper | undefined;

	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-option': ( position: string ) => `Option ${ position }`,
				'neowiki-property-editor-move-option-up': ( position: string ) => `Move option ${ position } up`,
				'neowiki-property-editor-move-option-down': ( position: string ) => `Move option ${ position } down`,
				'neowiki-property-editor-remove-option': ( position: string ) => `Remove option ${ position }`,
				'neowiki-property-editor-option-moved': ( position: string, count: string ) =>
					`Option moved to position ${ position } of ${ count }.`,
				'neowiki-property-editor-option-removed': 'Option removed.',
				'neowiki-property-editor-option-label-required': 'Every option needs a label.',
				'neowiki-property-editor-options-unique': 'Each option needs a different label.',
			},
			functions: [ 'config', 'message' ],
		} );
	} );

	afterEach( () => {
		wrapper?.unmount();
		wrapper = undefined;
	} );

	// Hands each change back as the property's new state, the way the property editor does.
	function newEditor(
		property: SelectProperty = newSelectProperty( { options: [ ACTIVE, INACTIVE, ACQUIRED ] } ),
		errorsRevealed = false,
	): VueWrapper {
		let current = property;

		wrapper = mount( SelectAttributesEditor, {
			props: {
				property,
				'onUpdate:property': ( changes: Partial<SelectProperty> ) => {
					current = { ...current, ...changes };
					return wrapper?.setProps( { property: current } );
				},
			},
			attachTo: document.body,
			global: {
				provide: { [ ErrorsRevealedKey as symbol ]: ref( errorsRevealed ) },
				mocks: { $i18n: createI18nMock() },
			},
		} );
		return wrapper;
	}

	function propertyOf( editor: VueWrapper ): SelectProperty {
		return ( editor.props() as { property: SelectProperty } ).property;
	}

	function optionsOf( editor: VueWrapper ): SelectOption[] {
		return propertyOf( editor ).options;
	}

	// The name a screen reader gives the field, however the editor supplies it.
	function accessibleName( input: HTMLInputElement ): string | undefined {
		return input.getAttribute( 'aria-label' ) ?? input.labels?.[ 0 ]?.textContent?.trim();
	}

	function labelFields( editor: VueWrapper ): DOMWrapper<HTMLInputElement>[] {
		return editor.findAll<HTMLInputElement>( 'input' )
			.filter( ( input ) => accessibleName( input.element )?.startsWith( 'Option ' ) );
	}

	function shownLabels( editor: VueWrapper ): string[] {
		return labelFields( editor ).map( ( input ) => input.element.value );
	}

	function labelField( editor: VueWrapper, position: number ): DOMWrapper<HTMLInputElement> {
		return labelFields( editor ).find( ( input ) => accessibleName( input.element ) === `Option ${ position }` )!;
	}

	function button( editor: VueWrapper, label: string ): DOMWrapper<HTMLButtonElement> {
		return editor.find<HTMLButtonElement>( `button[aria-label="${ label }"]` );
	}

	function addButton( editor: VueWrapper ): DOMWrapper<HTMLButtonElement> {
		return editor.findAll<HTMLButtonElement>( 'button' ).find( ( candidate ) => candidate.text() === ADD_OPTION )!;
	}

	async function press( pressed: DOMWrapper<HTMLButtonElement> ): Promise<void> {
		await pressed.trigger( 'click' );
		await flushPromises();
	}

	async function pressEnterIn( field: DOMWrapper<HTMLInputElement>, init: KeyboardEventInit = {} ): Promise<void> {
		await field.trigger( 'keydown', { key: 'Enter', ...init } );
		await flushPromises();
	}

	function shownErrors( editor: VueWrapper ): string[] {
		return editor.findAll( '.cdx-message--error' ).map( ( message ) => message.text() );
	}

	function announcement( editor: VueWrapper ): string {
		return editor.find( '[aria-live="polite"], [role="status"]' ).text();
	}

	// What the live region holds after each change to it, which is when a screen reader speaks.
	async function announcementsDuring( editor: VueWrapper, action: () => Promise<void> ): Promise<string[]> {
		const region = editor.find( '[aria-live="polite"], [role="status"]' ).element;
		const texts: string[] = [];
		const observer = new MutationObserver( () => texts.push( region.textContent ?? '' ) );
		observer.observe( region, { childList: true, characterData: true, subtree: true } );

		await action();
		await flushPromises();
		observer.disconnect();

		return texts;
	}

	it( 'shows the label of each option in a field of its own, in order', () => {
		expect( shownLabels( newEditor() ) ).toEqual( [ 'Active', 'Inactive', 'Acquired' ] );
	} );

	it( 'keeps the id of an option whose label changes', async () => {
		const editor = newEditor();

		await labelField( editor, 2 ).setValue( 'Dormant' );

		expect( optionsOf( editor ) ).toEqual( [ ACTIVE, { id: 'inactive', label: 'Dormant' }, ACQUIRED ] );
	} );

	it( 'hands back a label while it is typed, before its field is left', async () => {
		const editor = newEditor();
		const field = labelField( editor, 2 );

		// Not setValue(): it also fires `change`, which a field fires only once it is left.
		field.element.value = 'Dormant';
		await field.trigger( 'input' );

		expect( optionsOf( editor )[ 1 ].label ).toBe( 'Dormant' );
	} );

	it( 'keeps the ids of options that change places', async () => {
		const editor = newEditor();

		await press( button( editor, 'Move option 3 up' ) );

		expect( optionsOf( editor ) ).toEqual( [ ACTIVE, ACQUIRED, INACTIVE ] );
	} );

	it( 'moves an option down', async () => {
		const editor = newEditor();

		await press( button( editor, 'Move option 2 down' ) );

		expect( shownLabels( editor ) ).toEqual( [ 'Active', 'Acquired', 'Inactive' ] );
	} );

	it( 'cannot move its first option up or its last one down', () => {
		const editor = newEditor();

		expect( button( editor, 'Move option 1 up' ).element.disabled ).toBe( true );
		expect( button( editor, 'Move option 3 down' ).element.disabled ).toBe( true );
	} );

	it( 'keeps the ids of the other options when one is removed', async () => {
		const editor = newEditor();

		await press( button( editor, 'Remove option 2' ) );

		expect( optionsOf( editor ) ).toEqual( [ ACTIVE, ACQUIRED ] );
	} );

	it( 'adds an option without a label at the end, keeping the ids of the others', async () => {
		const editor = newEditor();

		await press( addButton( editor ) );

		expect( optionsOf( editor ).slice( 0, 3 ) ).toEqual( [ ACTIVE, INACTIVE, ACQUIRED ] );
		expect( optionsOf( editor )[ 3 ].label ).toBe( '' );
	} );

	it( 'adds an option without a label after the one Enter is pressed in', async () => {
		const editor = newEditor();

		await pressEnterIn( labelField( editor, 1 ) );

		expect( optionsOf( editor ) ).toEqual( [ ACTIVE, expect.objectContaining( { label: '' } ), INACTIVE, ACQUIRED ] );
	} );

	it( 'adds no option when Enter is pressed in one without a label', async () => {
		const editor = newEditor( newSelectProperty( { options: [ ACTIVE, { id: 'o2', label: ' ' } ] } ) );

		await pressEnterIn( labelField( editor, 2 ) );

		expect( optionsOf( editor ) ).toHaveLength( 2 );
	} );

	it( 'adds no option for the Enter that ends an input method composition', async () => {
		const editor = newEditor();

		await pressEnterIn( labelField( editor, 1 ), { isComposing: true } );

		expect( optionsOf( editor ) ).toEqual( [ ACTIVE, INACTIVE, ACQUIRED ] );
	} );

	it( 'gives an added option an id of `o` and 14 characters of the alphabet Subject ids use', async () => {
		const editor = newEditor( newSelectProperty() );

		await press( addButton( editor ) );

		expect( optionsOf( editor )[ 0 ].id ).toMatch( /^o[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{14}$/ );
	} );

	it( 'gives every added option an id no other option has', async () => {
		const editor = newEditor();

		await press( addButton( editor ) );
		await press( button( editor, 'Remove option 1' ) );
		await press( addButton( editor ) );

		const ids = optionsOf( editor ).map( ( option ) => option.id );
		expect( new Set( ids ).size ).toBe( ids.length );
	} );

	it( 'drops a removed option from the initial value', async () => {
		const editor = newEditor( newSelectProperty( {
			options: [ ACTIVE, INACTIVE, ACQUIRED ],
			multiple: true,
			default: newStringValue( [ ACTIVE.id, ACQUIRED.id ] ),
		} ) );

		await press( button( editor, 'Remove option 1' ) );

		expect( propertyOf( editor ).default ).toEqual( newStringValue( [ ACQUIRED.id ] ) );
	} );

	describe( 'focus', () => {
		it( 'goes to the label field of an added option', async () => {
			const editor = newEditor();

			await press( addButton( editor ) );

			expect( document.activeElement ).toBe( labelField( editor, 4 ).element );
		} );

		it( 'goes to the label field of an option added with Enter', async () => {
			const editor = newEditor();

			await pressEnterIn( labelField( editor, 1 ) );

			expect( document.activeElement ).toBe( labelField( editor, 2 ).element );
		} );

		it( 'stays on the button that moved the option', async () => {
			const editor = newEditor();

			await press( button( editor, 'Move option 3 up' ) );

			expect( document.activeElement ).toBe( button( editor, 'Move option 2 up' ).element );
		} );

		it.each( [
			[ 'Move option 2 up', 'Move option 1 down' ],
			[ 'Move option 2 down', 'Move option 3 up' ],
		] )( 'goes to the other move button once the option cannot move further: %s', async ( pressed, focused ) => {
			const editor = newEditor();

			await press( button( editor, pressed ) );

			expect( document.activeElement ).toBe( button( editor, focused ).element );
		} );

		it( 'goes to the option that takes the place of a removed one', async () => {
			const editor = newEditor( newSelectProperty( {
				options: [ ACTIVE, INACTIVE, ACQUIRED, { id: 'dissolved', label: 'Dissolved' } ],
			} ) );

			await press( button( editor, 'Remove option 2' ) );

			expect( document.activeElement ).toBe( button( editor, 'Remove option 2' ).element );
		} );

		it( 'goes to the option before a removed last one', async () => {
			const editor = newEditor();

			await press( button( editor, 'Remove option 3' ) );

			expect( document.activeElement ).toBe( button( editor, 'Remove option 2' ).element );
		} );

		it( 'goes to the button that adds an option once none is left', async () => {
			const editor = newEditor( newSelectProperty( { options: [ ACTIVE ] } ) );

			await press( button( editor, 'Remove option 1' ) );

			expect( document.activeElement ).toBe( addButton( editor ).element );
		} );
	} );

	describe( 'screen reader announcement', () => {
		it( 'says where an option moved to', async () => {
			const editor = newEditor();

			await press( button( editor, 'Move option 3 up' ) );

			expect( announcement( editor ) ).toBe( 'Option moved to position 2 of 3.' );
		} );

		it( 'says an option was removed', async () => {
			const editor = newEditor();

			await press( button( editor, 'Remove option 2' ) );

			expect( announcement( editor ) ).toBe( 'Option removed.' );
		} );

		it( 'says so again for a removal right after another', async () => {
			const editor = newEditor();
			await press( button( editor, 'Remove option 1' ) );

			const announcements = await announcementsDuring( editor, () => press( button( editor, 'Remove option 1' ) ) );

			expect( announcements ).toContain( 'Option removed.' );
		} );
	} );

	describe( 'faulty labels', () => {
		it( 'shows no error on an added option before its field is left', async () => {
			const editor = newEditor();

			await press( addButton( editor ) );

			expect( shownErrors( editor ) ).toEqual( [] );
			expect( labelField( editor, 4 ).element.closest( '.cdx-text-input--status-error' ) ).toBeNull();
		} );

		it( 'shows that an option needs a label once its field is left', async () => {
			const editor = newEditor();
			await press( addButton( editor ) );

			await labelField( editor, 4 ).trigger( 'blur' );

			expect( shownErrors( editor ) ).toEqual( [ 'Every option needs a label.' ] );
			expect( labelField( editor, 4 ).element.closest( '.cdx-text-input--status-error' ) ).not.toBeNull();
		} );

		it( 'shows that a label is taken, ignoring letter case and spaces, once the field is left', async () => {
			const editor = newEditor();
			await labelField( editor, 3 ).setValue( ' active ' );

			await labelField( editor, 3 ).trigger( 'blur' );

			expect( shownErrors( editor ) ).toEqual( [ 'Each option needs a different label.' ] );
		} );

		it( 'shows the error of every faulty option once a save was refused', () => {
			const editor = newEditor(
				newSelectProperty( { options: [ ACTIVE, { id: 'blank', label: '' }, { id: 'again', label: 'ACTIVE' } ] } ),
				true,
			);

			expect( shownErrors( editor ) ).toEqual( [
				'Each option needs a different label.',
				'Every option needs a label.',
				'Each option needs a different label.',
			] );
		} );
	} );

	it( 'emits multiple when the checkbox is toggled', async () => {
		const editor = newEditor();

		await editor.find( 'input[type="checkbox"]' ).setValue( true );

		expect( editor.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { multiple: true } ] );
	} );

	describe( 'Constraint severity', () => {
		function severityInputIn( editor: VueWrapper, selector: string ): VueWrapper<InstanceType<typeof SeverityInput>> {
			return ( editor.findComponent( selector ) as VueWrapper ).findComponent( SeverityInput );
		}

		it( 'shows the current severity of the options', () => {
			const editor = newEditor( {
				...newSelectProperty( { options: [ ACTIVE ] } ),
				constraintSeverities: { options: 'error' },
			} );

			expect( severityInputIn( editor, '.select-attributes__options' ).props( 'modelValue' ) ).toBe( 'error' );
		} );

		it( 'offers no severity while there are no options', () => {
			const editor = newEditor( newSelectProperty( { options: [] } ) );

			expect( severityInputIn( editor, '.select-attributes__options' ).exists() ).toBe( false );
		} );

		it( 'emits the changed severity of the options', async () => {
			const editor = newEditor( newSelectProperty( { options: [ ACTIVE ] } ) );

			await severityInputIn( editor, '.select-attributes__options' ).vm.$emit( 'update:modelValue', 'error' );

			expect( editor.emitted( 'update:property' ) ).toEqual( [ [ { constraintSeverities: { options: 'error' } } ] ] );
		} );

		it( 'offers a severity for the single-value rule while multiple values are not allowed', () => {
			const editor = newEditor( newSelectProperty( { multiple: false } ) );

			expect( severityInputIn( editor, '.select-attributes__multiple' ).exists() ).toBe( true );
		} );

		it( 'offers no severity for the single-value rule once multiple values are allowed', () => {
			const editor = newEditor( newSelectProperty( { multiple: true } ) );

			expect( severityInputIn( editor, '.select-attributes__multiple' ).exists() ).toBe( false );
		} );

		it( 'names the single-value rule rather than the checkbox that switches it off', () => {
			const editor = newEditor( newSelectProperty( { multiple: false } ) );

			expect( severityInputIn( editor, '.select-attributes__multiple' ).props( 'constraint' ) )
				.toBe( 'neowiki-property-editor-single-value' );
		} );

		it( 'shows the current severity of the single-value rule', () => {
			const editor = newEditor( {
				...newSelectProperty( { multiple: false } ),
				constraintSeverities: { multiple: 'error' },
			} );

			expect( severityInputIn( editor, '.select-attributes__multiple' ).props( 'modelValue' ) ).toBe( 'error' );
		} );

		it( 'emits the changed severity of the single-value rule, keeping the options\' annotation', async () => {
			const editor = newEditor( {
				...newSelectProperty( { options: [ ACTIVE ] } ),
				constraintSeverities: { options: 'error' },
			} );

			await severityInputIn( editor, '.select-attributes__multiple' ).vm.$emit( 'update:modelValue', 'error' );

			expect( editor.emitted( 'update:property' ) ).toEqual( [
				[ { constraintSeverities: { options: 'error', multiple: 'error' } } ],
			] );
		} );

		it( 'removes the annotation of the single-value rule when it goes back to warning', async () => {
			const editor = newEditor( {
				...newSelectProperty( { multiple: false } ),
				constraintSeverities: { multiple: 'error' },
			} );

			await severityInputIn( editor, '.select-attributes__multiple' ).vm.$emit( 'update:modelValue', 'warning' );

			expect( editor.emitted( 'update:property' ) ).toEqual( [ [ { constraintSeverities: undefined } ] ] );
		} );
	} );
} );
