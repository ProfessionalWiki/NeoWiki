import { MultiStringProperty, PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';

export interface UrlProperty extends MultiStringProperty {

	// readonly linkTarget?: '_blank' | '_self' | '_parent' | '_top';

}

export class UrlType extends BasePropertyType<UrlProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly typeName = 'url';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue(): StringValue {
		return newStringValue( 'https://example.com' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): UrlProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? false,
		} as UrlProperty;
	}

}

const ALLOWED_PROTOCOLS: readonly string[] = [ 'http:', 'https:' ];

export function formatUrlForDisplay( urlString: string, maxLength: number = 50 ): string {
	const stripped = stripProtocol( urlString );

	if ( stripped.length <= maxLength ) {
		return stripped;
	}

	return truncateUrl( stripped, maxLength );
}

function stripProtocol( urlString: string ): string {
	const url = parseHttpUrl( urlString );

	if ( url === null ) {
		return urlString;
	}

	const pathName = url.pathname === '/' ? '' : url.pathname;
	return url.hostname + pathName + url.search + url.hash;
}

export function isHttpUrl( urlString: string ): boolean {
	return parseHttpUrl( urlString ) !== null;
}

function parseHttpUrl( urlString: string ): URL | null {
	try {
		const url = new URL( urlString );
		return ALLOWED_PROTOCOLS.includes( url.protocol ) ? url : null;
	} catch {
		return null;
	}
}

function dropQueryAndFragment( url: string ): string {
	const questionMark = url.indexOf( '?' );
	const hash = url.indexOf( '#' );
	let end = url.length;

	if ( questionMark !== -1 ) {
		end = Math.min( end, questionMark );
	}
	// eslint-disable-next-line security/detect-possible-timing-attacks
	if ( hash !== -1 ) {
		end = Math.min( end, hash );
	}

	return url.slice( 0, end );
}

function truncateUrl( stripped: string, maxLength: number ): string {
	const withoutSuffix = dropQueryAndFragment( stripped );

	if ( withoutSuffix.length <= maxLength ) {
		return withoutSuffix;
	}

	const collapsed = collapseMiddleSegments( withoutSuffix, maxLength );

	if ( collapsed !== null ) {
		return collapsed;
	}

	return truncateMiddle( withoutSuffix, maxLength );
}

function collapseMiddleSegments( url: string, maxLength: number ): string | null {
	const firstSlash = url.indexOf( '/' );

	if ( firstSlash === -1 ) {
		return null;
	}

	const domain = url.slice( 0, firstSlash );
	const pathSegments = url.slice( firstSlash + 1 ).split( '/' ).filter( ( s ) => s !== '' );

	if ( pathSegments.length < 3 ) {
		return null;
	}

	const lastSegment = pathSegments[ pathSegments.length - 1 ];
	let best: string | null = null;

	for ( let frontCount = 0; frontCount < pathSegments.length - 1; frontCount++ ) {
		const frontPath = pathSegments.slice( 0, frontCount ).join( '/' );
		const prefix = frontCount > 0 ? domain + '/' + frontPath : domain;
		const candidate = prefix + '/\u2026/' + lastSegment;

		if ( candidate.length <= maxLength ) {
			best = candidate;
		} else {
			break;
		}
	}

	return best;
}

function truncateMiddle( text: string, maxLength: number ): string {
	if ( text.length <= maxLength ) {
		return text;
	}

	const frontLength = Math.ceil( ( maxLength - 1 ) * 0.6 );
	const backLength = maxLength - 1 - frontLength;

	return text.slice( 0, frontLength ) + '\u2026' + text.slice( -backLength );
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
