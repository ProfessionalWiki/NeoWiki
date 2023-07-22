import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type StringValue, ValueType } from '@/editor/domain/Value';
import {
	BaseValueFormat,
	createStringFormField,
	type ValidationError,
	ValidationResult
} from '@/editor/domain/ValueFormat';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';

export interface PhoneNumberProperty extends MultiStringProperty {
}

export class PhoneNumberFormat extends BaseValueFormat<PhoneNumberProperty, StringValue> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'phoneNumber';

	// TODO: unit tests
	public validate( value: StringValue, property: PhoneNumberProperty ): ValidationResult {
		const errors: ValidationError[] = [];

		// TODO: validate unique values
		// TODO: validate required?
		// TODO: validate multiple values?

		value.strings.forEach( ( string ) => {
			if ( !isValidPhoneNumber( string ) ) {
				errors.push( {
					message: `${string} is not a valid phone number`
				} );
			}
		} );

		return new ValidationResult( errors );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): PhoneNumberProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as PhoneNumberProperty;
	}

	public createFormField( value: StringValue | undefined, property: PhoneNumberProperty ): any {
		return createStringFormField( value, property, 'tel' );
	}

	public formatValueAsHtml( value: StringValue, property: PhoneNumberProperty ): string {
		return value.strings.join( ', ' );
	}

	public createTableEditorColumn( property: PhoneNumberProperty ): ColumnDefinition {
		const column = super.createTableEditorColumn( property );

		if ( property.multiple ) {
			column.formatter = ( cell: CellComponent ) => cell.getValue()?.join( ', ' );
		}

		return column;
	}

}

export function isValidPhoneNumber( phoneNumber: string ): boolean {
	const pattern = /^\s*(?:\+\d{1,3}\s?)?(?:\(\d{3}\)|\d{3})[\s.-]?\d{3}[\s.-]?\d{4}\s*$|^\s*\d{3,25}\s*$/;
	return pattern.test( phoneNumber );
}
