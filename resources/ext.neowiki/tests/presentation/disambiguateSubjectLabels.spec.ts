import { describe, expect, it } from 'vitest';
import { disambiguateSubjectLabels } from '@/presentation/disambiguateSubjectLabels.ts';
import type { SubjectLabelResult } from '@/domain/SubjectLabelSearch.ts';

const UNDECORATED = { showPageTitle: false, showId: false };

describe( 'disambiguateSubjectLabels', () => {
	it( 'leaves a lone result undecorated', () => {
		expect( disambiguateSubjectLabels( [
			{ id: 's1demo1aaaaaaa1', label: 'ACME Inc.', pageTitle: 'ACME Holdings' },
		] ) ).toEqual( [
			{ id: 's1demo1aaaaaaa1', label: 'ACME Inc.', pageTitle: 'ACME Holdings', ...UNDECORATED },
		] );
	} );

	it( 'leaves results nobody can confuse undecorated', () => {
		const disambiguated = disambiguateSubjectLabels( [
			{ id: 's1demo1aaaaaaa1', label: 'ACME Inc.', pageTitle: 'ACME Holdings' },
			{ id: 's1demo1aaaaaaa2', label: 'ACME GmbH', pageTitle: 'ACME Holdings' },
		] );

		expect( disambiguated.map( ( result ) => result.showPageTitle ) ).toEqual( [ false, false ] );
		expect( disambiguated.map( ( result ) => result.showId ) ).toEqual( [ false, false ] );
	} );

	it( 'shows the page of each result sharing its label', () => {
		const disambiguated = disambiguateSubjectLabels( [
			{ id: 's1demo1aaaaaaa1', label: 'Attendance', pageTitle: 'Rijksmuseum' },
			{ id: 's1demo1aaaaaaa2', label: 'Attendance', pageTitle: 'Louvre' },
		] );

		expect( disambiguated.map( ( result ) => result.showPageTitle ) ).toEqual( [ true, true ] );
		expect( disambiguated.map( ( result ) => result.showId ) ).toEqual( [ false, false ] );
	} );

	it( 'shows no page beside a result whose own label is unique to the list', () => {
		const disambiguated = disambiguateSubjectLabels( [
			{ id: 's1demo1aaaaaaa1', label: 'Attendance', pageTitle: 'Rijksmuseum' },
			{ id: 's1demo1aaaaaaa2', label: 'Attendance 2019', pageTitle: 'Rijksmuseum' },
			{ id: 's1demo1aaaaaaa3', label: 'Attendance', pageTitle: 'Louvre' },
		] );

		expect( disambiguated[ 1 ] ).toEqual(
			{ id: 's1demo1aaaaaaa2', label: 'Attendance 2019', pageTitle: 'Rijksmuseum', ...UNDECORATED },
		);
	} );

	// A page titled after its Main Subject is what the one-step create flow leaves behind, so this
	// is the ordinary shape rather than an oddity.
	it( 'shows no page that merely repeats the label it sits under', () => {
		const disambiguated = disambiguateSubjectLabels( [
			{ id: 's1demo1aaaaaaa1', label: 'Rembrandt', pageTitle: 'Rembrandt' },
			{ id: 's1demo1aaaaaaa2', label: 'Rembrandt', pageTitle: 'Rembrandt van Rijn' },
		] );

		expect( disambiguated.map( ( result ) => result.showPageTitle ) ).toEqual( [ false, true ] );
	} );

	// Asserted whole: the pair sharing a page keeps its ids although a third row does not share it,
	// which a rule reading "every row in one place" would get wrong.
	it( 'shows the id only to the rows sharing a page, where a namesake sits elsewhere', () => {
		expect( disambiguateSubjectLabels( [
			{ id: 's1demo1aaaaaaa1', label: 'Appellation', pageTitle: 'Rembrandt' },
			{ id: 's1demo1aaaaaaa2', label: 'Appellation', pageTitle: 'Rembrandt' },
			{ id: 's1demo1aaaaaaa3', label: 'Appellation', pageTitle: 'Vermeer' },
		] ) ).toEqual( [
			{
				id: 's1demo1aaaaaaa1',
				label: 'Appellation',
				pageTitle: 'Rembrandt',
				showPageTitle: true,
				showId: true,
			},
			{
				id: 's1demo1aaaaaaa2',
				label: 'Appellation',
				pageTitle: 'Rembrandt',
				showPageTitle: true,
				showId: true,
			},
			{
				id: 's1demo1aaaaaaa3',
				label: 'Appellation',
				pageTitle: 'Vermeer',
				showPageTitle: true,
				showId: false,
			},
		] );
	} );

	it( 'compares labels exactly on case, which a reader can see for themselves', () => {
		const disambiguated = disambiguateSubjectLabels( [
			{ id: 's1demo1aaaaaaa1', label: 'Appellation', pageTitle: 'Rembrandt' },
			{ id: 's1demo1aaaaaaa2', label: 'appellation', pageTitle: 'Rembrandt' },
		] );

		expect( disambiguated.map( ( result ) => result.showPageTitle ) ).toEqual( [ false, false ] );
		expect( disambiguated.map( ( result ) => result.showId ) ).toEqual( [ false, false ] );
	} );

	it( 'counts labels differing only in whitespace as one, HTML drawing them the same', () => {
		const disambiguated = disambiguateSubjectLabels( [
			{ id: 's1demo1aaaaaaa1', label: 'Van Gogh', pageTitle: 'Vincent van Gogh' },
			{ id: 's1demo1aaaaaaa2', label: 'Van  Gogh ', pageTitle: 'Theo van Gogh' },
		] );

		expect( disambiguated.map( ( result ) => result.showPageTitle ) ).toEqual( [ true, true ] );
	} );

	it( 'counts a composed and a decomposed accent as one label, drawing the same glyphs', () => {
		const disambiguated = disambiguateSubjectLabels( [
			{ id: 's1demo1aaaaaaa1', label: 'Bronté', pageTitle: 'Charlotte Bronte' },
			{ id: 's1demo1aaaaaaa2', label: 'Bronté', pageTitle: 'Emily Bronte' },
		] );

		expect( disambiguated.map( ( result ) => result.showPageTitle ) ).toEqual( [ true, true ] );
	} );

	it( 'withholds a page that repeats its label through whitespace alone', () => {
		const disambiguated = disambiguateSubjectLabels( [
			{ id: 's1demo1aaaaaaa1', label: 'Rembrandt ', pageTitle: 'Rembrandt' },
			{ id: 's1demo1aaaaaaa2', label: 'Rembrandt', pageTitle: 'Rembrandt van Rijn' },
		] );

		expect( disambiguated.map( ( result ) => result.showPageTitle ) ).toEqual( [ false, true ] );
	} );

	// A backend predating the page title, or an extension supplying its own search, answers without
	// one. Such a row must name no page rather than emptying the whole search.
	it( 'names no page for a result that carries none, and still answers', () => {
		const withoutPage = [
			{ id: 's1demo1aaaaaaa1', label: 'Attendance' },
			{ id: 's1demo1aaaaaaa2', label: 'Attendance' },
		] as unknown as SubjectLabelResult[];

		const disambiguated = disambiguateSubjectLabels( withoutPage );

		expect( disambiguated.map( ( result ) => result.showPageTitle ) ).toEqual( [ false, false ] );
		expect( disambiguated.map( ( result ) => result.showId ) ).toEqual( [ true, true ] );
	} );

} );
