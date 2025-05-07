<template>
	<div class="ext-neowiki-schema-editor__property-editor">
		<CdxField>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-name' ).text() }}
			</template>
			<CdxTextInput
				:model-value="localProperty.name.toString()"
				input-type="text"
				@update:model-value="updatePropertyName"
			/>
		</CdxField>

		<CdxField>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-type' ).text() }}
			</template>
			<CdxSelect
				v-model:selected="localProperty.type"
				:menu-items="typeOptions"
			/>
		</CdxField>

		<CdxField>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-description' ).text() }}
			</template>
			<CdxTextArea
				v-model="localProperty.description"
			/>
		</CdxField>

		<CdxField>
			<CdxToggleSwitch
				v-model="localProperty.required">
				{{ $i18n( 'neowiki-property-editor-required' ).text() }}
			</CdxToggleSwitch>
		</CdxField>

		<component
			:is="componentRegistry.getAttributesEditor( localProperty.type )"
			:property="localProperty"
			@update:property="updatePropertyAttributes"
		/>
		<component
			:is="componentRegistry.getValueEditingComponent( localProperty.type )"
			v-model="localProperty.default"
			class="property-definition-editor__default"
			:label="$i18n( 'neowiki-property-editor-initial-value' ).text()"
			:property="localProperty"
		/>
	</div>
</template>

<script setup lang="ts">
import { PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition.ts';
import { CdxField, CdxSelect, CdxTextArea, CdxTextInput, CdxToggleSwitch } from '@wikimedia/codex';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { ref, watch } from 'vue';

const props = defineProps<{
	property: PropertyDefinition;
}>();

const emit = defineEmits<{
	'update:property-definition': [ PropertyDefinition ];
}>();

const localProperty = ref<PropertyDefinition>( { ...props.property } );

watch(
	localProperty,
	( newValue ) => {
		emit( 'update:property-definition', newValue as PropertyDefinition );
	},
	{ deep: true }
);

function updatePropertyName( name: string ): void {
	if ( !PropertyName.isValid( name ) ) {
		console.log( 'TODO: show error' );
		return;
	}

	localProperty.value = {
		...localProperty.value,
		name: new PropertyName( name )
	};
}

function updatePropertyAttributes<T extends PropertyDefinition>( attributes: Partial<T> ): void {
	localProperty.value = {
		...localProperty.value,
		...attributes
	};
}

const componentRegistry = NeoWikiServices.getComponentRegistry();

const typeOptions = componentRegistry.getLabelsAndIcons().map( ( { value, label } ) => ( {
	value: value,
	label: mw.message( label ).text()
} ) );
</script>
