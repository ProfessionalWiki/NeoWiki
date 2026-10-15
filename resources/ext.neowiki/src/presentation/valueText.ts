import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { type Value, ValueType } from '@/domain/Value';
import type { NumberProperty } from '@/domain/propertyTypes/Number';
import { resolveSelectLabel, type SelectProperty, SelectType } from '@/domain/propertyTypes/Select';
import { DateType, formatDateForDisplay } from '@/domain/propertyTypes/Date';
import { DateTimeType, formatDateTimeForDisplay } from '@/domain/propertyTypes/DateTime';

/**
 * A value as one line of plain text, read the way its property type shows it, such as the label of a select option
 * rather than its id. Null for a value that reads as no text: a relation, one of a type this wiki does not know, or
 * one without a part that holds text.
 */
export function valueText( property: PropertyDefinition, value: Value ): string | null {
	switch ( value.type ) {
		case ValueType.String:
			return joined( value.parts.filter( isText ).map( ( part ) => stringText( property, part ) ) );
		case ValueType.Number:
			return numberText( property as NumberProperty, value.number );
		case ValueType.Boolean:
			return mw.message( value.boolean ? 'neowiki-boolean-true' : 'neowiki-boolean-false' ).text();
		case ValueType.MonolingualText:
			return joined( value.parts.map( ( part ) => part.text ).filter( isText ) );
		default:
			return null;
	}
}

function stringText( property: PropertyDefinition, part: string ): string {
	switch ( property.type ) {
		case SelectType.typeName:
			return resolveSelectLabel( property as SelectProperty, part ) ?? mw.message( 'neowiki-select-unknown-option' ).text();
		case DateType.typeName:
			return formatDateForDisplay( part );
		case DateTimeType.typeName:
			return formatDateTimeForDisplay( part );
		default:
			return part;
	}
}

function numberText( property: NumberProperty, number: number ): string {
	return property.precision === undefined ? number.toString() : number.toFixed( property.precision );
}

function isText( text: string ): boolean {
	return text.trim() !== '';
}

function joined( texts: string[] ): string | null {
	return texts.length === 0 ? null : texts.join( ', ' );
}
