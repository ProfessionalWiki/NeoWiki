<template>
	<CdxField :hide-label="true">
		<CdxToggleSwitch
			:model-value="property.multiple"
			:align-switch="true"
			:label="$i18n( 'neowiki-property-editor-multiple' ).text()"
			@update:model-value="onUpdate"
		>
			{{ $i18n( 'neowiki-property-editor-multiple' ).text() }}
		</CdxToggleSwitch>
	</CdxField>
</template>

<script setup lang="ts">
import { CdxField, CdxToggleSwitch } from '@wikimedia/codex';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';

interface MultiCapableProperty extends PropertyDefinition {
	readonly multiple?: boolean;
}

defineProps<{
	property: MultiCapableProperty;
}>();

const emit = defineEmits<{
	'update:property': [ Partial<MultiCapableProperty> ];
}>();

function onUpdate( value: boolean ): void {
	emit( 'update:property', { multiple: value } );
}
</script>
