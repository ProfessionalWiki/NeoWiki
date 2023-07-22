import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type StringValue, ValueType } from '@/editor/domain/Value';
import {
	BaseValueFormat,
	createStringFormField,
	type ValidationError,
	ValidationResult
} from '@/editor/domain/ValueFormat';
import DOMPurify from 'dompurify';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';

export interface UrlProperty extends MultiStringProperty {
}

export class UrlFormat extends BaseValueFormat<UrlProperty, StringValue> {

	public readonly valueType = ValueType.String;
	public readonly name = 'url';

	// TODO: unit tests
	public validate( value: StringValue, property: UrlProperty ): ValidationResult {
		const errors: ValidationError[] = [];

		// TODO: validate unique values
		// TODO: validate required?
		// TODO: validate multiple values?

		value.strings.forEach( ( url ) => {
			if ( !isValidUrl( url ) ) {
				errors.push( {
					message: `${url} is not a valid URL`
				} );
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

	public createFormField( value: StringValue | undefined, property: UrlProperty ): any {
		return createStringFormField( value, property, 'url' );
	}

	public formatValueAsHtml( value: StringValue, property: UrlProperty ): string {
		return value.strings.map( ( urlString ) => {
			try {
				const url = new URL( urlString );
				const sanitizedUrl = url.href;
				const pathName = url.pathname === '/' ? '' : url.pathname;
				const displayedUrl = url.hostname + pathName + url.search + url.hash;
				return `<a href="${sanitizedUrl}">${displayedUrl}</a>`; // TODO: add CSS classes?
			} catch ( _ ) {
				return DOMPurify.sanitize( urlString ); // TODO: add styling and CSS classes?
			}
		} ).filter( Boolean ).join( ', ' );
	}

	public createTableEditorColumn( property: UrlProperty ): ColumnDefinition {
		const column: ColumnDefinition = super.createTableEditorColumn( property );

		if ( property.multiple ) {
			column.formatter = ( cell: CellComponent ) => cell.getValue()?.join( ', ' );
		} else {
			column.formatter = 'link';
			column.formatterParams = {
				target: '_blank',
				label: ( cell: CellComponent ) => {
					const val = cell.getValue();
					return typeof val === 'string' || val instanceof String ? val.replace( /^https?:\/\//, '' ) : val;
				}
			};
		}

		return column;
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
