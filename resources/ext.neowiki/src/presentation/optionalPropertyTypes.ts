import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';
import type { FrontendRegistrar } from '@/presentation/FrontendRegistrar';
import { EdtfType } from '@/domain/propertyTypes/Edtf';
import EdtfDisplay from '@/components/Value/EdtfDisplay.vue';
import TextInput from '@/components/Value/TextInput.vue';
import BooleanAttributesEditor from '@/components/SchemaEditor/Property/BooleanAttributesEditor.vue';
import { cdxIconCalendar } from '@wikimedia/codex-icons';

const edtfType = new EdtfType();

export const OPTIONAL_PROPERTY_TYPES: readonly PropertyTypeRegistration[] = [
	{
		typeName: EdtfType.typeName,
		valueType: EdtfType.valueType,
		displayAttributeNames: edtfType.getDisplayAttributeNames(),
		createPropertyDefinitionFromJson: ( base ) => edtfType.createPropertyDefinitionFromJson( base ),
		getExampleValue: () => edtfType.getExampleValue(),
		displayComponent: EdtfDisplay,
		inputComponent: TextInput,
		attributesEditor: BooleanAttributesEditor,
		label: 'neowiki-property-type-edtf',
		icon: cdxIconCalendar,
	},
];

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
