import { beforeEach, describe, expect, it } from 'vitest';
import { valueText } from '@/presentation/valueText';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { newNumberProperty } from '@/domain/propertyTypes/Number';
import { newBooleanProperty } from '@/domain/propertyTypes/Boolean';
import { newSelectProperty } from '@/domain/propertyTypes/Select';
import { formatDateForDisplay, newDateProperty } from '@/domain/propertyTypes/Date';
import { newMonolingualTextProperty } from '@/domain/propertyTypes/MonolingualText';
import { newRelationProperty } from '@/domain/propertyTypes/Relation';
import {
	newBooleanValue,
	newMonolingualTextValue,
	newNumberValue,
	newRelation,
	newStringValue,
	RelationValue,
} from '@/domain/Value';
import { setupMwMock } from '../VueTestHelpers';

describe( 'valueText', () => {

	beforeEach( () => {
		setupMwMock();
	} );

	it( 'reads the parts of a text value one after the other, blank ones left out', () => {
		expect( valueText( newTextProperty(), newStringValue( 'Pablo', ' ', 'Ruiz' ) ) ).toBe( 'Pablo, Ruiz' );
	} );

	it( 'reads a number with the precision of its property', () => {
		expect( valueText( newNumberProperty( { precision: 2 } ), newNumberValue( 2024 ) ) ).toBe( '2024.00' );
	} );

	it( 'reads a yes-or-no value as a word', () => {
		expect( valueText( newBooleanProperty(), newBooleanValue( true ) ) ).toBe( 'neowiki-boolean-true' );
	} );

	it( 'reads the label of the option chosen, not its id', () => {
		const property = newSelectProperty( { options: [ { id: 'o1', label: 'Birth name' }, { id: 'o2', label: 'Pseudonym' } ] } );

		expect( valueText( property, newStringValue( 'o2' ) ) ).toBe( 'Pseudonym' );
	} );

	it( 'reads a date as dates are shown', () => {
		expect( valueText( newDateProperty(), newStringValue( '1881-10-25' ) ) ).toBe( formatDateForDisplay( '1881-10-25' ) );
	} );

	it( 'reads the texts of a monolingual text value', () => {
		const value = newMonolingualTextValue( [ { language: 'en', text: 'Guernica' }, { language: 'es', text: 'Guernica (Picasso)' } ] );

		expect( valueText( newMonolingualTextProperty(), value ) ).toBe( 'Guernica, Guernica (Picasso)' );
	} );

	it( 'reads a relation as no text', () => {
		expect( valueText( newRelationProperty(), new RelationValue( [ newRelation( undefined, 's1aaaaaaaaaaaa1' ) ] ) ) ).toBeNull();
	} );

	it( 'reads a value without a part that holds text as none', () => {
		expect( valueText( newTextProperty(), newStringValue( ' ' ) ) ).toBeNull();
	} );

} );
