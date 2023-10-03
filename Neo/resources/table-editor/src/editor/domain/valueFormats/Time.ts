import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/editor/domain/Value';
import {
	type TagMultiselectWidget,
	TagMultiselectWidgetFactory
} from '@/editor/presentation/Widgets/TagMultiselectWidgetFactory';
import type { ValidationError } from '@/editor/domain/ValueFormat';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import type { TextProperty } from '@/editor/domain/valueFormats/Text';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';

export interface TimeProperty extends MultiStringProperty {
	// TODO: allow multiple values? Date and DateTime do not, neither does PHP
}

export interface TimeAttributes extends PropertyAttributes {
}

export class TimeFormat extends BaseValueFormat<TimeProperty, StringValue, OO.ui.InputWidget, TimeAttributes> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'time';

	public getExampleValue(): StringValue {
		return newStringValue( '11:10.14' );
	}

	// TODO: unit tests
	public validate( value: StringValue, property: TimeProperty ): ValidationResult {
		const errors: ValidationError[] = [];

		// TODO: validate unique values
		// TODO: validate required?
		// TODO: validate multiple values?

		value.strings.forEach( ( string ) => {
			if ( !isValidTime( string ) ) {
				errors.push( {
					message: `${string} is not a valid time`,
					value: newStringValue( string )
				} );
			}
		} );

		return new ValidationResult( errors );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): TimeProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as TimeProperty;
	}

	public createFormField( value: StringValue | undefined, property: TimeProperty ): OO.ui.Widget {
		value = value ?? {
			type: ValueType.String,
			strings: []
		};

		if ( property.multiple ) { // FIXME: this does not have time handling
			return TagMultiselectWidgetFactory.create( {
				selected: value.strings,
				allowArbitrary: true,
				allowDuplicates: !property.uniqueItems,
				allowEditTags: true,
				allowReordering: true,
				required: property.required
			} );
		}

		return new mw.widgets.datetime.DateTimeInputWidget( {
			type: 'time',
			value: value.strings.join( '' ),
			required: property.required
		} );
	}

	public getFieldData( field: OO.ui.InputWidget ): StringValue {
		const value = field.getValue();
		return value !== '' ? newStringValue( value ) : newStringValue();
	}

	public createTableEditorColumn( property: TextProperty ): ColumnDefinition {
		const column = super.createTableEditorColumn( property );

		if ( property.multiple ) {
			column.formatter = ( cell: CellComponent ) => cell.getValue()?.join( ', ' );
		}

		return column;
	}

	public getAttributes( base: PropertyAttributes ): TimeAttributes {
		return {
			...base
		};
	}

	public getFieldElement( field: OO.ui.Widget, property: TimeProperty ): HTMLInputElement {
		if ( property.multiple ) {
			const multipleField = field as TagMultiselectWidget;
			return multipleField.input.$input[ 0 ] as HTMLInputElement;
		}
		return ( field as OO.ui.TextInputWidget ).$input[ 0 ] as HTMLInputElement;
	}
}

export function isValidTime( time: string ): boolean {
	const pattern = /^([0-9]{1,2}):?([0-9]{2}):?([0-9]{2})$/;
	return pattern.test( time );
}
