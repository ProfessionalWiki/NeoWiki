<template>
	<div class="ext-neowiki-schema-editor__property-editor">
		<CdxField>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-name' ).text() }}
			</template>
			<CdxTextInput
				:model-value="property.name.toString()"
				input-type="text"
				@update:model-value="updateName"
			/>
		</CdxField>

		<CdxField>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-type' ).text() }}
			</template>
			<CdxSelect
				v-model:selected="props.property.type"
				:menu-items="typeOptions"
			/>
		</CdxField>

		<CdxField>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-description' ).text() }}
			</template>
			<CdxTextArea
				:model-value="property.description"
			/>
		</CdxField>

		<CdxField>
			<CdxToggleSwitch
				:model-value="property.required">
				{{ $i18n( 'neowiki-property-editor-required' ).text() }}
			</CdxToggleSwitch>
		</CdxField>

		<component
			:is="componentRegistry.getAttributesEditor( property.type )"
			:property="property"
			@update:property="console.log( 'update:property', property )"
		/>
		<component
			:is="componentRegistry.getValueEditingComponent( property.type )"
			v-model="property.default"
			class="property-definition-editor__default"
			:label="$i18n( 'neowiki-property-editor-initial-value' ).text()"
			:property="property"
		/>
	</div>
</template>

<script setup lang="ts">
import { PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition.ts';
import { CdxField, CdxSelect, CdxTextArea, CdxTextInput, CdxToggleSwitch } from '@wikimedia/codex';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = defineProps<{
	property: PropertyDefinition;
}>();

const emit = defineEmits<{
	'update:property-definition': [ PropertyDefinition ];
}>();

const componentRegistry = NeoWikiServices.getComponentRegistry();

const typeOptions = componentRegistry.getLabelsAndIcons().map( ( { value, label } ) => ( {
	value: value,
	label: mw.message( label ).text()
} ) );

function updateName( name: string ): void {
	emit( 'update:property-definition', { ...props.property, name: new PropertyName( name ) } );
}

function updateType( type: string ): void {
	emit( 'update:property-definition', { ...props.property, type: type } );
}

function updateDescription( description: string ): void {
	emit( 'update:property-definition', { ...props.property, description: description } );
}

function updateRequired( required: boolean ): void {
	emit( 'update:property-definition', { ...props.property, required: required } );
}
</script>
