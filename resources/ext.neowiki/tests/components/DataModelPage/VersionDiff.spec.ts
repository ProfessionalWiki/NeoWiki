import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import VersionDiff from '@/components/DataModelPage/VersionDiff.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

describe( 'VersionDiff', () => {

	beforeEach( () => {
		setupMwMock();
	} );

	it( 'shows a change in a long item with the lines around it, not the whole item', () => {
		const lines = Array.from( { length: 40 }, ( _value, index ) => `line ${ index + 1 }` );
		const wrapper = mount( VersionDiff, {
			props: { here: lines.join( '\n' ), upstream: lines.map( ( line ) => line === 'line 30' ? 'line 30 changed' : line ).join( '\n' ) },
			global: { mocks: { $i18n: createI18nMock() } },
		} );

		expect( wrapper.findAll( '.ext-neowiki-version-diff__line' ).map( ( line ) => line.text() ) ).toEqual( [
			'line 27', 'line 28', 'line 29', '− line 30', '+ line 30 changed', 'line 31', 'line 32', 'line 33',
		] );
	} );

} );
