import { mount, VueWrapper } from '@vue/test-utils';
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
} );
