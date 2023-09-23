import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/editor/domain/Value';
import {
	createStringFormField, getTagFieldData, getTextFieldData,
	BaseValueFormat,
	type ValidationError,
	ValidationResult
} from '@/editor/domain/ValueFormat';
import type { FieldData } from '@/editor/presentation/SchemaForm';
import type { TagMultiselectWidget } from '@/editor/presentation/Widgets/TagMultiselectWidgetFactory';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';

export interface PhoneNumberProperty extends MultiStringProperty {
}

export interface PhoneNumberAttributes extends PropertyAttributes {
	readonly multiple?: boolean;
}

export class PhoneNumberFormat extends BaseValueFormat<PhoneNumberProperty, StringValue, TagMultiselectWidget|OO.ui.TextInputWidget, PhoneNumberAttributes> {

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
					message: `${string} is not a valid phone number`,
					value: newStringValue( string )
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

	public createFormField( value: StringValue | undefined, property: PhoneNumberProperty ): OO.ui.Widget {
		return createStringFormField( value, property, 'tel' );
	}

	public async getFieldData( field: TagMultiselectWidget|OO.ui.TextInputWidget, property: PropertyDefinition ): Promise<FieldData> {
		if ( field instanceof OO.ui.TagMultiselectWidget ) {
			return getTagFieldData( field, property );
		}
		return await getTextFieldData( field );
	}

	public createTableEditorColumn( property: PhoneNumberProperty ): ColumnDefinition {
		const column = super.createTableEditorColumn( property );

		if ( property.multiple ) {
			column.formatter = ( cell: CellComponent ) => cell.getValue()?.join( ', ' );
		}

		return column;
	}

	public getAttributes( base: PropertyAttributes ): PhoneNumberAttributes {
		return {
			...base,
			multiple: false
		};
	}
}

export function isValidPhoneNumber( phoneNumber: string ): boolean {
	const pattern = /^\s*(?:\+\d{1,3}\s?)?(?:\(\d{3}\)|\d{3})[\s.-]?\d{3}[\s.-]?\d{4}\s*$|^\s*\d{3,25}\s*$/;
	return pattern.test( phoneNumber );
}
