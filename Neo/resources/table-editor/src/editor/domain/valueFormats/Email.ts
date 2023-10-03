import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/editor/domain/Value';
import {
	BaseValueFormat,
	getTextFieldData,
	type ValidationError,
	ValidationResult
} from '@/editor/domain/ValueFormat';
import type { TagMultiselectWidget } from '@/editor/presentation/Widgets/TagMultiselectWidgetFactory';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import {
	type MultipleTextInputWidget,
	MultipleTextInputWidgetFactory
} from '@/editor/presentation/Widgets/MultipleTextInputWidgetFactory';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';
import { isValidPhoneNumber } from '@/editor/domain/valueFormats/PhoneNumber';

export interface EmailProperty extends MultiStringProperty {
}

interface EmailAttributes extends PropertyAttributes {
	readonly multiple?: boolean;
}

export class EmailFormat extends BaseValueFormat<EmailProperty, StringValue, TagMultiselectWidget|OO.ui.TextInputWidget, EmailAttributes> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'email';

	public getExampleValue(): StringValue {
		return newStringValue( 'example@email.com' );
	}

	public validate( value: StringValue, property: EmailProperty ): ValidationResult {
		const errors: ValidationError[] = [];

		value.strings.forEach( ( string ) => {
			if ( !isValidEmail( string ) ) {
				errors.push( {
					message: `${string} is not a valid email`,
					value: newStringValue( string )
				} );
			}
		} );

		return new ValidationResult( errors );
	}

	public validateMultipleField( fields: Set<HTMLInputElement>, value: StringValue, property: EmailProperty ): ValidationResult {
		if ( fields === undefined || !property.multiple ) {
			return new ValidationResult( [] );
		}

		const errors: ValidationError[] = [];

		fields.forEach( ( input ) => {
			const inputValue = input.value.trim();

			if ( inputValue !== '' && !isValidEmail( inputValue ) ) {
				errors.push( {
					message: `${inputValue} is not a valid email`,
					value: newStringValue( inputValue )
				} );
			} else {
				fields.delete( input );
			}
		} );

		return new ValidationResult( errors );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): EmailProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as EmailProperty;
	}

	public createFormField( value: StringValue | undefined, property: EmailProperty ): OO.ui.Widget {
		if ( property.multiple ) {
			return MultipleTextInputWidgetFactory.create( {
				type: this.getFormatName(),
				typeFormat: this,
				values: value?.strings ?? [],
				required: property.required
			} );
		}

		return new OO.ui.TextInputWidget( {
			type: this.getFormatName(),
			value: value?.strings[ 0 ] ?? '',
			required: property.required
		} );
	}

	public getFieldData( field: OO.ui.Widget ): StringValue {
		if ( Object.prototype.hasOwnProperty.call( field, 'multiple' ) ) {
			return ( field as MultipleTextInputWidget ).getFieldData();
		}
		return getTextFieldData( field as OO.ui.TextInputWidget );
	}

	public createTableEditorColumn( property: EmailProperty ): ColumnDefinition {
		const column = super.createTableEditorColumn( property );

		if ( property.multiple ) {
			column.formatter = ( cell: CellComponent ) => cell.getValue()?.join( ', ' );
		}

		return column;
	}

	public getAttributes( base: PropertyAttributes ): EmailAttributes {
		return {
			...base,
			multiple: false
		};
	}

	public getFieldElement( field: OO.ui.Widget, property: EmailProperty ): HTMLInputElement|Set<HTMLInputElement> {
		if ( property.multiple ) {
			const multipleField = field as MultipleTextInputWidget;
			return new Set( [ ...multipleField.$wrapper.find( `input[type="${multipleField.type}"]` ) ] as HTMLInputElement[] );
		}
		return ( field as OO.ui.TextInputWidget ).$input[ 0 ] as HTMLInputElement;
	}
}

export function isValidEmail( email: string ): boolean {
	return Boolean( email.match(
		/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/ // eslint-disable-line
	) );
}
