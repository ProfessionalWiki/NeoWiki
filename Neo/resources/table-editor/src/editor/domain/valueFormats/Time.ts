import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type StringValue, ValueType } from '@/editor/domain/Value';
import { TagMultiselectWidgetFactory } from '@/editor/presentation/Widgets/TagMultiselectWidgetFactory';
import type { ValidationError } from '@/editor/domain/ValueFormat';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';

export interface TimeProperty extends MultiStringProperty {
}

export class TimeFormat extends BaseValueFormat<TimeProperty, StringValue> {

	public readonly valueType = ValueType.String;
	public readonly name = 'time';

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

	public createFormField( value: StringValue | undefined, property: TimeProperty ): any {
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
				allowReordering: true
				// TODO: handle required?
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

	public formatValueAsHtml( value: StringValue, property: TimeProperty ): string {
		return value.strings.join( ', ' );
	}

}

export function isValidTime( time: string ): boolean {
	const pattern = /^([0-9]{1,2}):?([0-9]{2}):?([0-9]{2})$/;
	return pattern.test( time );
}
