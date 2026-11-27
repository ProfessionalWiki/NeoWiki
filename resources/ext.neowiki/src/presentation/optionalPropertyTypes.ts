import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';
import type { FrontendRegistrar } from '@/presentation/FrontendRegistrar';

export const OPTIONAL_PROPERTY_TYPES: readonly PropertyTypeRegistration[] = [];

export function registerEnabledPropertyTypes(
	registrar: FrontendRegistrar,
	optionalTypes: readonly PropertyTypeRegistration[],
): void {
	const enabledTypes = ( mw.config.get( 'wgNeoWikiEnabledPropertyTypes' ) ?? [] ) as string[];

	for ( const type of optionalTypes ) {
		if ( enabledTypes.includes( type.typeName ) ) {
			registrar.registerPropertyType( type );
		}
	}
}
