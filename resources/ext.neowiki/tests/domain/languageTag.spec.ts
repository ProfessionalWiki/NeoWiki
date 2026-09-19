import { describe, expect, it } from 'vitest';
import { isValidLanguageTag } from '@/domain/languageTag';

describe( 'isValidLanguageTag', () => {

	it.each( [ 'eu', 'und', 'pt-BR', 'zh-Hant-TW' ] )( 'accepts %s', ( tag ) => {
		expect( isValidLanguageTag( tag ) ).toBe( true );
	} );

	it.each( [ '', 'pt_BR', 'eu ', 'eu-', 'esperantoo', '1u', 'not a language' ] )( 'rejects %s', ( tag ) => {
		expect( isValidLanguageTag( tag ) ).toBe( false );
	} );

} );
