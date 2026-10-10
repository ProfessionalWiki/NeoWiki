import { mount, VueWrapper, DOMWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import SummaryAction from '@/components/common/SummaryAction.vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconCheck, cdxIconTrash } from '@wikimedia/codex-icons';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

describe( 'SummaryAction', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'msg' ] } );
	} );

	function mountComponent( props: Partial<InstanceType<typeof SummaryAction>[ '$props' ]> = {} ): VueWrapper {
		return mount( SummaryAction, {
			props: {
				helpText: '',
				saveButtonLabel: 'Save',
				saveDisabled: false,
				...props,
			},
			global: {
				mocks: { $i18n },
			},
		} );
	}

	it( 'disables save button when saveDisabled is true', () => {
		const wrapper = mountComponent( { saveDisabled: true } );

		const button = wrapper.findComponent( CdxButton );
		expect( button.attributes( 'disabled' ) ).toBe( '' );
	} );

	it( 'enables save button when saveDisabled is false', () => {
		const wrapper = mountComponent( { saveDisabled: false } );

		const button = wrapper.findComponent( CdxButton );
		expect( button.attributes( 'disabled' ) ).toBeUndefined();
	} );

	it( 'emits save with summary when button is clicked', async () => {
		const wrapper = mountComponent( { saveDisabled: false } );

		await wrapper.findComponent( CdxButton ).trigger( 'click' );

		expect( wrapper.emitted( 'save' ) ).toEqual( [ [ '' ] ] );
	} );

	it( 'defaults the save button action to progressive', () => {
		const wrapper = mountComponent();

		const button = wrapper.findComponent( CdxButton );
		expect( button.props( 'action' ) ).toBe( 'progressive' );
	} );

	it( 'forwards the saveButtonAction prop to the save button', () => {
		const wrapper = mountComponent( { saveButtonAction: 'destructive' } );

		const button = wrapper.findComponent( CdxButton );
		expect( button.props( 'action' ) ).toBe( 'destructive' );
	} );

	it( 'defaults the save button icon to cdxIconCheck', () => {
		const wrapper = mountComponent();

		const icon = wrapper.findComponent( CdxIcon );
		expect( icon.props( 'icon' ) ).toBe( cdxIconCheck );
	} );

	it( 'forwards the saveButtonIcon prop to the save button', () => {
		const wrapper = mountComponent( { saveButtonIcon: cdxIconTrash } );

		const icon = wrapper.findComponent( CdxIcon );
		expect( icon.props( 'icon' ) ).toBe( cdxIconTrash );
	} );

	it( 'defaults the field label to the edit-summary message', () => {
		const wrapper = mountComponent();

		expect( wrapper.text() ).toContain( 'neowiki-edit-summary-label' );
	} );

	it( 'uses the provided label prop over the default', () => {
		const wrapper = mountComponent( { label: 'Reason' } );

		expect( wrapper.text() ).toContain( 'Reason' );
		expect( wrapper.text() ).not.toContain( 'neowiki-edit-summary-label' );
	} );

	it( 'defaults the field placeholder to the edit-summary message', () => {
		const wrapper = mountComponent();

		expect( wrapper.find( 'textarea' ).attributes( 'placeholder' ) )
			.toBe( 'neowiki-edit-summary-placeholder' );
	} );

	it( 'uses the provided placeholder prop over the default', () => {
		const wrapper = mountComponent( { placeholder: 'Why are you deleting this?' } );

		expect( wrapper.find( 'textarea' ).attributes( 'placeholder' ) )
			.toBe( 'Why are you deleting this?' );
	} );

	describe( 'Footer text', () => {
		function actionRow( wrapper: VueWrapper ): Omit<DOMWrapper<Element>, 'exists'> {
			return wrapper.get( '.ext-neowiki-summary-action__actions' );
		}

		// Without a note there is no extra node in the row, and no class the stylesheet keys
		// the inline layout off.
		it( 'leaves the action row untouched when no footer text is given', () => {
			const wrapper = mountComponent();

			expect( actionRow( wrapper ).classes() ).toEqual( [ 'ext-neowiki-summary-action__actions' ] );
			expect( actionRow( wrapper ).element.children ).toHaveLength( 1 );
			expect( wrapper.find( '.ext-neowiki-summary-action__footer-text' ).exists() ).toBe( false );
		} );

		it( 'renders the footer text in the action row, ahead of the button', () => {
			const wrapper = mountComponent( { footerText: 'Saving updates 2 subjects' } );

			const row = actionRow( wrapper );
			expect( row.classes() ).toContain( 'ext-neowiki-summary-action__actions--with-text' );
			expect( row.element.children ).toHaveLength( 2 );
			expect( row.element.children[ 0 ].textContent?.trim() ).toBe( 'Saving updates 2 subjects' );
		} );

		// The block above the row keeps its own slot: the two are shown together.
		it( 'renders the footer text alongside the help text rather than in its place', () => {
			const wrapper = mountComponent( { helpText: 'Help', footerText: 'Footer' } );

			expect( wrapper.get( '.ext-neowiki-summary-action__help-text' ).text() ).toBe( 'Help' );
			expect( wrapper.get( '.ext-neowiki-summary-action__footer-text' ).text() ).toBe( 'Footer' );
		} );
	} );
} );
