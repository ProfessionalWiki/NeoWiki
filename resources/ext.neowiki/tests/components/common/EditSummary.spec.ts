import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import EditSummary from '@/components/common/EditSummary.vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconCheck, cdxIconTrash } from '@wikimedia/codex-icons';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

describe( 'EditSummary', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'msg' ] } );
	} );

	function mountComponent( props: Partial<InstanceType<typeof EditSummary>[ '$props' ]> = {} ): VueWrapper {
		return mount( EditSummary, {
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
} );
