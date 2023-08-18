import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/editor/domain/Value';
import { TagMultiselectWidgetFactory } from '@/editor/presentation/Widgets/TagMultiselectWidgetFactory';
import type { ValidationError } from '@/editor/domain/ValueFormat';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';
import type { FieldData } from '@/editor/presentation/SchemaForm';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import type { TextProperty } from '@/editor/domain/valueFormats/Text';

export interface TimeProperty extends MultiStringProperty {
}

export class TimeFormat extends BaseValueFormat<TimeProperty, StringValue, OO.ui.InputWidget> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'time';

	// TODO: unit tests
	public validate( value: StringValue, property: TimeProperty ): ValidationResult {
		const errors: ValidationError[] = [];

		// TODO: validate unique values
		// TODO: validate required?
		// TODO: validate multiple values?

		value.strings.forEach( ( string ) => {
			if ( !isValidTime( string ) ) {
				errors.push( {
					message: `${string} is not a valid time`
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

		const widget = new mw.widgets.datetime.DateTimeInputWidget( {
			type: 'time',
			value: value.strings.join( '' ),
			required: property.required
		} );

		widget.setFlags( { invalid: false } );

		return widget;
	}

	public async getFieldData( field: OO.ui.InputWidget ): Promise<FieldData> {
		const value = field.getValue();

		return {
			value: value !== '' ? newStringValue( value ) : newStringValue(),
			valid: true,
			errorMessage: undefined
		};
	}

	public createTableEditorColumn( property: TextProperty ): ColumnDefinition {
		const column = super.createTableEditorColumn( property );

		if ( property.multiple ) {
			column.formatter = ( cell: CellComponent ) => cell.getValue()?.join( ', ' );
		}

		return column;
	}
}

export function isValidTime( time: string ): boolean {
	const pattern = /^([0-9]{1,2}):?([0-9]{2}):?([0-9]{2})$/;
	return pattern.test( time );
}
