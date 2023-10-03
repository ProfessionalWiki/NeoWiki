import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/editor/domain/Value';
import {
	getTextFieldData,
	BaseValueFormat,
	type ValidationError,
	ValidationResult
} from '@/editor/domain/ValueFormat';
import type { TagMultiselectWidget } from '@/editor/presentation/Widgets/TagMultiselectWidgetFactory';
import DOMPurify from 'dompurify';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import {
	type MultipleTextInputWidget,
	MultipleTextInputWidgetFactory
} from '@/editor/presentation/Widgets/MultipleTextInputWidgetFactory';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';

export interface UrlProperty extends MultiStringProperty {

	// TODO: add link target (_blank, _self, etc.)

}

export interface UrlAttributes extends PropertyAttributes {
	readonly multiple?: boolean;
}

export class UrlFormat extends BaseValueFormat<UrlProperty, StringValue, TagMultiselectWidget|OO.ui.TextInputWidget, UrlAttributes> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'url';

	public getExampleValue(): StringValue {
		return newStringValue( 'https://example.com' );
	}

	// TODO: unit tests
	public validate( value: StringValue, property: UrlProperty ): ValidationResult {
		const errors: ValidationError[] = [];

		value.strings.forEach( ( string ) => {
			if ( !isValidUrl( string ) ) {
				errors.push( {
					message: `${string} is not a valid URL`,
					value: newStringValue( string )
				} );
			}
		} );

		return new ValidationResult( errors );
	}

	public validateMultipleField( fields: Set<HTMLInputElement>, value: StringValue, property: UrlProperty ): ValidationResult {
		if ( fields === undefined || !property.multiple ) {
			return new ValidationResult( [] );
		}

		const errors: ValidationError[] = [];

		fields.forEach( ( input ) => {
			const inputValue = input.value.trim();

			if ( inputValue !== '' && !isValidUrl( inputValue ) ) {
				errors.push( {
					message: `${inputValue} is not a valid URL`,
					value: newStringValue( inputValue )
				} );
			} else {
				fields.delete( input );
			}
		} );

		return new ValidationResult( errors );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): UrlProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as UrlProperty;
	}

	public createFormField( value: StringValue | undefined, property: UrlProperty ): OO.ui.Widget {
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

	public getFieldData( field: OO.ui.Widget, property: PropertyDefinition ): StringValue {
		if ( Object.prototype.hasOwnProperty.call( field, 'multiple' ) ) {
			return ( field as MultipleTextInputWidget ).getFieldData();
		}
		return getTextFieldData( field as OO.ui.TextInputWidget );
	}

	public createTableEditorColumn( property: UrlProperty ): ColumnDefinition {
		const column: ColumnDefinition = super.createTableEditorColumn( property );

		column.formatter = function ( cell: CellComponent ) {
			const value = cell.getValue();

			if ( Array.isArray( value ) && typeof value[ 0 ] === 'string' ) {
				return ( new UrlFormatter( property ) ).formatUrlArrayAsHtml( value );
			}

			return '';
		};

		return column;
	}

	public getAttributes( base: PropertyAttributes ): UrlAttributes {
		return {
			...base,
			multiple: false
		};
	}

	public getFieldElement( field: OO.ui.Widget, property: UrlProperty ): HTMLInputElement|Set<HTMLInputElement> {
		if ( property.multiple ) {
			const multipleField = field as MultipleTextInputWidget;
			return new Set( [ ...multipleField.$wrapper.find( `input[type="${multipleField.type}"]` ) ] as HTMLInputElement[] );
		}
		return ( field as OO.ui.TextInputWidget ).$input[ 0 ] as HTMLInputElement;
	}

}

export class UrlFormatter {

	public constructor(
		private readonly property: UrlProperty
	) {
	}

	public formatUrlArrayAsHtml( urls: string[] ): string {
		return urls.map( this.formatUrlAsHtml.bind( this ) )
			.filter( ( v: string ) => v.trim() !== '' )
			.join( ', ' );
	}

	public formatUrlAsHtml( urlString: string ): string {
		try {
			const url = new URL( urlString );
			const sanitizedUrl = url.href;
			const displayedUrl = this.urlStringToDisplayValue( urlString );
			return `<a href="${sanitizedUrl}">${displayedUrl}</a>`; // TODO: add CSS classes?
		} catch ( _ ) {
			return DOMPurify.sanitize( urlString ); // TODO: add styling and CSS classes?
		}
	}

	private urlStringToDisplayValue( urlString: string ): string {
		try {
			const url = new URL( urlString );
			const pathName = url.pathname === '/' ? '' : url.pathname;
			return url.hostname + pathName + url.search + url.hash;
		} catch ( _ ) {
			return DOMPurify.sanitize( urlString );
		}
	}
}

export function isValidUrl( url: string ): boolean {
	const pattern = new RegExp(
		'^(https?://)?' +
		'((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|' +
		'((\\d{1,3}\\.){3}\\d{1,3})|' +
		'(localhost))' +
		'(\\:\\d+)?' +
		'(\\/[-a-z\\d%_.~+]*)*' +
		'(\\?[;&a-z\\d%_.~+=-]*)?' +
		'(\\#[-a-z\\d_]*)?$',
		'i'
	);

	return pattern.test( url );
}
