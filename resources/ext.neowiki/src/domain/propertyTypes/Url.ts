import { MultiStringProperty, PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/domain/Value';
import { BasePropertyType, ValueValidationError } from '@/domain/PropertyType';

export interface UrlProperty extends MultiStringProperty {

	// readonly linkTarget?: '_blank' | '_self' | '_parent' | '_top';

}

export class UrlType extends BasePropertyType<UrlProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly typeName = 'url';

	public getExampleValue(): StringValue {
		return newStringValue( 'https://example.com' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): UrlProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true,
		} as UrlProperty;
	}

	public validate( value: StringValue | undefined, property: UrlProperty ): ValueValidationError[] {
		const errors: ValueValidationError[] = [];
		value = value === undefined ? newStringValue() : value;

		if ( property.required && value.parts.length === 0 ) {
			errors.push( { code: 'required' } );
			return errors;
		}

		// TODO: check property.multiple

		for ( const part of value.parts ) {
			const url = part.trim();

			if ( url !== '' && !isValidUrl( url ) ) {
				errors.push( { code: 'invalid-url', source: part } );
			}
		}

		if ( property.uniqueItems && new Set( value.parts ).size !== value.parts.length ) {
			errors.push( { code: 'unique' } ); // TODO: add source
		}

		return errors;
	}

}

const ALLOWED_PROTOCOLS: readonly string[] = [ 'http:', 'https:' ];

function hasAllowedProtocol( url: URL ): boolean {
	return ALLOWED_PROTOCOLS.includes( url.protocol );
}

export class UrlFormatter {

	public constructor(
		private readonly property: UrlProperty,
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

			if ( !hasAllowedProtocol( url ) ) {
				return escapeHtml( urlString );
			}

			const sanitizedUrl = escapeHtml( url.href );
			const displayedUrl = escapeHtml( this.urlStringToDisplayValue( urlString ) );
			return `<a href="${ sanitizedUrl }">${ displayedUrl }</a>`; // TODO: add CSS classes?
		} catch ( _ ) {
			return escapeHtml( urlString ); // TODO: add styling and CSS classes?
		}
	}

	private urlStringToDisplayValue( urlString: string ): string {
		try {
			const url = new URL( urlString );
			const pathName = url.pathname === '/' ? '' : url.pathname;
			return url.hostname + pathName + url.search + url.hash;
		} catch ( _ ) {
			return urlString;
		}
	}
}

function escapeHtml( text: string ): string {
	return text
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#039;' );
}

export function isValidUrl( urlString: string ): boolean {
	const protocolMatch = urlString.match( /^([a-z][a-z\d+.-]*):\/\//i );
	if ( protocolMatch && !ALLOWED_PROTOCOLS.includes( protocolMatch[ 1 ].toLowerCase() + ':' ) ) {
		return false;
	}

	const pattern = new RegExp(
		'^([a-z][a-z\\d+.-]*://)?' +
		'((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|' +
		'((\\d{1,3}\\.){3}\\d{1,3})|' +
		'(localhost))' +
		'(\\:\\d+)?' +
		'(\\/[-a-z\\d%_.~+]*)*' +
		'(\\?[;&a-z\\d%_.~+=-]*)?' +
		'(\\#[-a-z\\d_]*)?$',
		'i',
	);

	return pattern.test( urlString );
}

type UrlPropertyAttributes = Omit<Partial<UrlProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newUrlProperty( attributes: UrlPropertyAttributes = {} ): UrlProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Url' ),
		type: UrlType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		multiple: attributes.multiple ?? false,
		uniqueItems: attributes.uniqueItems ?? true,
	};
}
