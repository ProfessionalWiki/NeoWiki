import { describe, expect, it } from 'vitest';
import { chosenSubjectName } from '@/domain/chosenSubjectName';

const SUBJECT_ID = 's11111111111aaa';

describe( 'chosenSubjectName', () => {
	it( 'is the page name for the main subject', () => {
		expect( chosenSubjectName( false, 'Acme Anvil', [] ) ).toBe( 'Acme Anvil' );
	} );

	it( 'is nothing for a subject beside a main subject', () => {
		expect( chosenSubjectName( true, 'Acme Anvil', [] ) ).toBeNull();
	} );

	it( 'is nothing where the page has yet to be titled', () => {
		expect( chosenSubjectName( false, null, [] ) ).toBeNull();
	} );

	it( 'is nothing where the page is titled after a subject it holds', () => {
		expect( chosenSubjectName( false, SUBJECT_ID, [ SUBJECT_ID ] ) ).toBeNull();
	} );

	/**
	 * A wiki that capitalizes page titles - the default - stores the page an id titles under an
	 * upper-case S, so that is the title this rule most often meets.
	 */
	it( 'is nothing where that title was capitalized by the wiki', () => {
		expect( chosenSubjectName( false, 'S11111111111aaa', [ SUBJECT_ID ] ) ).toBeNull();
	} );

	it( 'is the page name where the page holds no subject of that id', () => {
		expect( chosenSubjectName( false, SUBJECT_ID, [ 's11111111111bbb' ] ) ).toBe( SUBJECT_ID );
	} );

	/**
	 * An ordinary word can read like an id - fifteen letters starting with an s - and naming a page
	 * after one must not unname its Subject.
	 */
	it( 'is the page name where the title merely looks like an id', () => {
		expect( chosenSubjectName( false, 'Standardization', [ SUBJECT_ID ] ) ).toBe( 'Standardization' );
	} );
} );
