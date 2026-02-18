import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import EditSummary from '@/components/common/EditSummary.vue';
import { CdxButton } from '@wikimedia/codex';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

describe( 'EditSummary', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'msg' ] } );
	} );

	function mountComponent( saveDisabled: boolean ): VueWrapper {
		return mount( EditSummary, {
			props: {
				helpText: '',
				saveButtonLabel: 'Save',
				saveDisabled,
			},
			global: {
				mocks: { $i18n },
			},
		} );
	}

	it( 'disables save button when saveDisabled is true', () => {
		const wrapper = mountComponent( true );

		const button = wrapper.findComponent( CdxButton );
		expect( button.attributes( 'disabled' ) ).toBe( '' );
	} );

	it( 'enables save button when saveDisabled is false', () => {
		const wrapper = mountComponent( false );

		const button = wrapper.findComponent( CdxButton );
		expect( button.attributes( 'disabled' ) ).toBeUndefined();
	} );

	it( 'emits save with summary when button is clicked', async () => {
		const wrapper = mountComponent( false );

		await wrapper.findComponent( CdxButton ).trigger( 'click' );

		expect( wrapper.emitted( 'save' ) ).toEqual( [ [ '' ] ] );
	} );
} );
