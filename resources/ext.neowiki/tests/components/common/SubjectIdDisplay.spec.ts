import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, VueWrapper } from '@vue/test-utils';
import SubjectIdDisplay from '@/components/common/SubjectIdDisplay.vue';
import { SubjectId } from '@/domain/SubjectId.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const SUBJECT_ID = 's1ab2cd3ef4gh5i';

describe( 'SubjectIdDisplay', () => {

	let writeText: ReturnType<typeof vi.fn>;

	beforeEach( () => {
		setupMwMock( { functions: [ 'msg', 'notify' ] } );
		writeText = vi.fn().mockResolvedValue( undefined );
		Object.defineProperty( navigator, 'clipboard', { value: { writeText }, configurable: true } );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	function mountId(): VueWrapper {
		return mount( SubjectIdDisplay, {
			props: { subjectId: new SubjectId( SUBJECT_ID ) },
			global: { mocks: { $i18n: createI18nMock() } },
		} );
	}

	it( 'shows the id', () => {
		expect( mountId().text() ).toBe( SUBJECT_ID );
	} );

	it( 'copies the id and says what it copied', async () => {
		await mountId().get( 'button' ).trigger( 'click' );

		expect( writeText ).toHaveBeenCalledWith( SUBJECT_ID );
		expect( mw.notify ).toHaveBeenCalledWith(
			'neowiki-subject-id-copied' + SUBJECT_ID,
			{ type: 'success' },
		);
	} );

	// A clipboard write is refused outright in some browsers and permission setups.
	it( 'reports a refused copy instead of claiming success', async () => {
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		writeText.mockRejectedValue( new Error( 'clipboard denied' ) );

		await mountId().get( 'button' ).trigger( 'click' );
		await Promise.resolve();

		expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-subject-id-copy-error', { type: 'error' } );
	} );

	it( 'names what the button copies, for assistive technology and on hover', () => {
		const button = mountId().get( 'button' );

		expect( button.attributes( 'aria-label' ) ).toBe( 'neowiki-subject-id-copy' + SUBJECT_ID );
		expect( button.attributes( 'title' ) ).toContain( SUBJECT_ID );
	} );

} );
