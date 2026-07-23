import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MappingCreatorDialog from '@/components/MappingsPage/MappingCreatorDialog.vue';
import { createI18nMock } from '../../VueTestHelpers.ts';

const createMock = vi.fn();
const notifyMock = vi.fn();

const CdxDialogStub = {
	template: '<div v-if="open" class="cdx-dialog-stub"><slot /><slot name="footer" /></div>',
	props: [ 'open', 'title', 'useCloseButton' ],
	emits: [ 'update:open' ],
};

const SummaryActionStub = {
	template: '<button class="edit-summary-stub" @click="$emit( \'save\', \'\' )">save</button>',
	emits: [ 'save' ],
};

function mountDialog(): VueWrapper {
	return mount( MappingCreatorDialog, {
		props: { open: true },
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: {
				CdxDialog: CdxDialogStub,
				SummaryAction: SummaryActionStub,
			},
		},
	} );
}

async function enterNameAndSave( wrapper: VueWrapper, name: string ): Promise<void> {
	await wrapper.find( 'input' ).setValue( name );
	await wrapper.find( '.edit-summary-stub' ).trigger( 'click' );
}

// Uses the real SummaryAction so pressing Enter exercises its exposed submit
// (which saves with whatever summary is currently entered).
function mountDialogWithRealSummaryAction(): VueWrapper {
	return mount( MappingCreatorDialog, {
		props: { open: true },
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: { CdxDialog: CdxDialogStub },
		},
	} );
}

describe( 'MappingCreatorDialog', () => {
	beforeEach( () => {
		createMock.mockReset();
		notifyMock.mockReset();
		vi.stubGlobal( 'mw', {
			msg: vi.fn( ( key: string, ...params: string[] ) => ( params.length ? key + params.join( '' ) : key ) ),
			notify: notifyMock,
			Api: vi.fn( function ( this: { create: typeof createMock } ) {
				this.create = createMock;
			} ),
		} );
	} );

	it( 'rejects an empty name without calling the API', async () => {
		const wrapper = mountDialog();

		await enterNameAndSave( wrapper, '   ' );

		expect( createMock ).not.toHaveBeenCalled();
		expect( wrapper.text() ).toContain( 'neowiki-mapping-creator-name-required' );
	} );

	it( 'rejects the reserved name "native" case-insensitively without calling the API', async () => {
		const wrapper = mountDialog();

		await enterNameAndSave( wrapper, 'Native' );

		expect( createMock ).not.toHaveBeenCalled();
		expect( wrapper.text() ).toContain( 'neowiki-mapping-creator-name-reserved' );
	} );

	it( 'creates the Mapping page with the skeleton and emits created on success', async () => {
		createMock.mockResolvedValue( {} );
		const wrapper = mountDialog();

		await enterNameAndSave( wrapper, 'EDM' );
		await flushPromises();

		expect( createMock ).toHaveBeenCalledWith(
			'Mapping:EDM',
			{ summary: 'neowiki-mapping-creator-summary-default' },
			'{"version": 1, "prefixes": {}, "schemas": {}}',
		);
		expect( wrapper.emitted( 'created' ) ).toEqual( [ [ 'EDM' ] ] );
	} );

	it( 'submits when Enter is pressed in the name field', async () => {
		createMock.mockResolvedValue( {} );
		const wrapper = mountDialogWithRealSummaryAction();

		await wrapper.find( 'input' ).setValue( 'EDM' );
		await wrapper.find( 'input' ).trigger( 'keyup.enter' );
		await flushPromises();

		expect( createMock ).toHaveBeenCalled();
		expect( wrapper.emitted( 'created' ) ).toEqual( [ [ 'EDM' ] ] );
	} );

	it( 'does not submit again while a save is in flight', async () => {
		let resolveCreate!: ( value: unknown ) => void;
		createMock.mockReturnValue( new Promise( ( resolve ) => {
			resolveCreate = resolve;
		} ) );
		const wrapper = mountDialogWithRealSummaryAction();

		await wrapper.find( 'input' ).setValue( 'EDM' );
		await wrapper.find( 'input' ).trigger( 'keyup.enter' );
		await wrapper.find( 'input' ).trigger( 'keyup.enter' );
		resolveCreate( {} );
		await flushPromises();

		expect( createMock ).toHaveBeenCalledTimes( 1 );
		expect( wrapper.emitted( 'created' ) ).toEqual( [ [ 'EDM' ] ] );
	} );

	it( 'shows a name-taken error and does not emit created when the page already exists', async () => {
		createMock.mockRejectedValue( 'articleexists' );
		const wrapper = mountDialog();

		await enterNameAndSave( wrapper, 'EDM' );
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-mapping-creator-name-taken' );
		expect( wrapper.emitted( 'created' ) ).toBeUndefined();
	} );

	it( 'notifies on an unexpected error and does not emit created', async () => {
		createMock.mockRejectedValue( new Error( 'boom' ) );
		const wrapper = mountDialog();

		await enterNameAndSave( wrapper, 'EDM' );
		await flushPromises();

		expect( notifyMock ).toHaveBeenCalled();
		expect( wrapper.emitted( 'created' ) ).toBeUndefined();
	} );
} );
