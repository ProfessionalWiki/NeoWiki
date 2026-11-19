import { describe, expect, it } from 'vitest';
import { LayoutDeserializer } from '@/persistence/LayoutDeserializer';
import { LayoutSerializer } from '@/persistence/LayoutSerializer';
import { readReleaseContent, releaseContentPages } from './releaseContent';

describe( 'Layouts as releases stored them', () => {

	it.each( releaseContentPages( 'Layout' ) )( 'reads $page ($release) as stored', ( { release, page } ) => {
		const stored = readReleaseContent( release, 'Layout', page );
		const layout = new LayoutDeserializer().deserialize( page, stored );

		expect( JSON.parse( new LayoutSerializer().serializeLayout( layout ) ) ).toEqual( stored );
	} );

} );
