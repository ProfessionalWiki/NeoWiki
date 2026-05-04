<template>
	<CdxField
		v-if="property.multiple"
		:hide-label="true"
	>
		<CdxToggleSwitch
			:model-value="property.uniqueItems"
			:align-switch="true"
			:label="$i18n( 'neowiki-property-editor-unique-items' ).text()"
			@update:model-value="onUpdate"
		>
			{{ $i18n( 'neowiki-property-editor-unique-items' ).text() }}
		</CdxToggleSwitch>
	</CdxField>
</template>

<script setup lang="ts">
import { CdxField, CdxToggleSwitch } from '@wikimedia/codex';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';

interface UniqueCapableProperty extends PropertyDefinition {
	readonly multiple?: boolean;
	readonly uniqueItems?: boolean;
}

defineProps<{
	property: UniqueCapableProperty;
}>();

const emit = defineEmits<{
	'update:property': [ Partial<UniqueCapableProperty> ];
}>();

function onUpdate( value: boolean ): void {
	emit( 'update:property', { uniqueItems: value } );
}
</script>
