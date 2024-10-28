import { MultiStringProperty, PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@neo/domain/Value';
import { BaseValueFormat } from '@neo/domain/ValueFormat';
import DOMPurify from 'dompurify';

export interface UrlProperty extends MultiStringProperty {

	// readonly linkTarget?: '_blank' | '_self' | '_parent' | '_top';

}

export class UrlFormat extends BaseValueFormat<UrlProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly formatName = 'url';

	public getExampleValue(): StringValue {
		return newStringValue( 'https://example.com' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): UrlProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as UrlProperty;
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
			return `<a href="${ sanitizedUrl }">${ displayedUrl }</a>`; // TODO: add CSS classes?
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

type UrlPropertyAttributes = Omit<Partial<UrlProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newUrlProperty( attributes: UrlPropertyAttributes = {} ): UrlProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Url' ),
		format: UrlFormat.formatName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		multiple: attributes.multiple ?? false,
		uniqueItems: attributes.uniqueItems ?? true
	};
}
