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

	// TODO: add link target (_blank, _self, etc.)

}

export class UrlFormat extends BaseValueFormat<UrlProperty, StringValue> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'url';

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
		return ( new UrlFormatter( property ) ).formatUrlArrayAsHtml( value.strings );
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
