import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxMessage } from '@wikimedia/codex';
import SubjectViolationBanners from '@/components/common/SubjectViolationBanners.vue';
import type { SubjectViolation } from '@/domain/SubjectViolation';

function violation( overrides: Partial<SubjectViolation> = {} ): SubjectViolation {
	return {
		propertyName: null,
		code: 'schema-not-found',
		args: [],
		severity: 'error',
		valuePartIndex: null,
		...overrides,
	};
}

function newWrapper( violations: SubjectViolation[] ): ReturnType<typeof mount> {
	return mount( SubjectViolationBanners, {
		props: { violations },
	} );
}

describe( 'SubjectViolationBanners', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( key: string, ...params: string[] ) => ( {
				text: () => [ key, ...params ].join( '|' ),
			} ) ),
		} );
	} );

	it( 'renders nothing without violations', () => {
		const wrapper = newWrapper( [] );

		expect( wrapper.findAllComponents( CdxMessage ) ).toHaveLength( 0 );
	} );

	it( 'renders error violations in a single error banner', () => {
		const wrapper = newWrapper( [
			violation( { code: 'schema-not-found', args: [ 'Person' ] } ),
			violation( { code: 'type-mismatch', args: [ 'text', 'number' ] } ),
		] );

		const banners = wrapper.findAllComponents( CdxMessage );
		expect( banners ).toHaveLength( 1 );
		expect( banners[ 0 ].props( 'type' ) ).toBe( 'error' );
		expect( banners[ 0 ].text() ).toContain( 'neowiki-field-schema-not-found|Person' );
		expect( banners[ 0 ].text() ).toContain( 'neowiki-field-type-mismatch|text|number' );
	} );

	it( 'renders warning violations in a warning banner', () => {
		const wrapper = newWrapper( [ violation( { code: 'required', severity: 'warning' } ) ] );

		const banners = wrapper.findAllComponents( CdxMessage );
		expect( banners ).toHaveLength( 1 );
		expect( banners[ 0 ].props( 'type' ) ).toBe( 'warning' );
		expect( banners[ 0 ].text() ).toContain( 'neowiki-field-required' );
	} );

	it( 'partitions mixed severities into an error banner followed by a warning banner', () => {
		const wrapper = newWrapper( [
			violation( { code: 'required', severity: 'warning' } ),
			violation( { code: 'type-mismatch', args: [ 'text', 'number' ], severity: 'error' } ),
		] );

		const banners = wrapper.findAllComponents( CdxMessage );
		expect( banners ).toHaveLength( 2 );
		expect( banners[ 0 ].props( 'type' ) ).toBe( 'error' );
		expect( banners[ 0 ].text() ).toContain( 'neowiki-field-type-mismatch|text|number' );
		expect( banners[ 1 ].props( 'type' ) ).toBe( 'warning' );
		expect( banners[ 1 ].text() ).toContain( 'neowiki-field-required' );
	} );
} );
