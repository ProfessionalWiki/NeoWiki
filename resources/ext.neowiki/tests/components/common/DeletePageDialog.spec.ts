import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DeletePageDialog from '@/components/common/DeletePageDialog.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import { cdxIconTrash } from '@wikimedia/codex-icons';
import { createI18nMock } from '../../VueTestHelpers.ts';

const getEditTokenMock = vi.fn();
const postMock = vi.fn();
const notifyMock = vi.fn();

const CdxDialogStub = {
	template: '<div v-if="open" class="cdx-dialog-stub"><slot /><slot name="footer" /></div>',
	props: [ 'open', 'title', 'useCloseButton' ],
	emits: [ 'update:open' ],
};

const SummaryActionStub = {
	template: '<button class="summary-action-stub" @click="$emit( \'save\', \'\' )">save</button>',
	props: [ 'saveButtonAction', 'saveButtonIcon', 'saveButtonLabel', 'saveDisabled', 'label', 'placeholder', 'helpText' ],
	emits: [ 'save' ],
};

function mountDialog( props: Record<string, unknown> = {} ): VueWrapper {
	return mount( DeletePageDialog, {
		props: {
			open: true,
			pageTitle: 'Schema:Person',
			displayName: 'Person',
			...props,
		},
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: {
				CdxDialog: CdxDialogStub,
				SummaryAction: SummaryActionStub,
				I18nSlot: true,
			},
		},
	} );
}

describe( 'DeletePageDialog', () => {
	beforeEach( () => {
		getEditTokenMock.mockReset().mockResolvedValue( 'token' );
		postMock.mockReset().mockResolvedValue( {} );
		notifyMock.mockReset();
		vi.stubGlobal( 'mw', {
			msg: vi.fn( ( key: string, ...params: string[] ) => ( params.length ? key + params.join( '' ) : key ) ),
			notify: notifyMock,
			Api: vi.fn( function ( this: { getEditToken: typeof getEditTokenMock; post: typeof postMock } ) {
				this.getEditToken = getEditTokenMock;
				this.post = postMock;
			} ),
		} );
	} );

	it( 'configures the confirm button as destructive with the trash icon', () => {
		const action = mountDialog().findComponent( SummaryAction );

		expect( action.props( 'saveButtonAction' ) ).toBe( 'destructive' );
		expect( action.props( 'saveButtonIcon' ) ).toBe( cdxIconTrash );
	} );

	it( 'deletes the page with the entered reason and emits deleted on success', async () => {
		const wrapper = mountDialog();

		wrapper.findComponent( SummaryAction ).vm.$emit( 'save', 'spam page' );
		await flushPromises();

		expect( postMock ).toHaveBeenCalledWith( expect.objectContaining( {
			action: 'delete',
			title: 'Schema:Person',
			reason: 'spam page',
			token: 'token',
		} ) );
		expect( notifyMock ).toHaveBeenCalled();
		expect( wrapper.emitted( 'deleted' ) ).toHaveLength( 1 );
		expect( wrapper.emitted( 'update:open' ) ).toContainEqual( [ false ] );
	} );

	it( 'falls back to the default summary when no reason is entered', async () => {
		const wrapper = mountDialog();

		wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( postMock ).toHaveBeenCalledWith( expect.objectContaining( {
			reason: 'neowiki-delete-summary-default',
		} ) );
	} );

	it( 'notifies an error and does not emit deleted when the delete fails', async () => {
		postMock.mockRejectedValue( new Error( 'boom' ) );
		const wrapper = mountDialog();

		wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( notifyMock ).toHaveBeenCalledWith( 'boom', expect.objectContaining( { type: 'error' } ) );
		expect( wrapper.emitted( 'deleted' ) ).toBeUndefined();
	} );
} );
