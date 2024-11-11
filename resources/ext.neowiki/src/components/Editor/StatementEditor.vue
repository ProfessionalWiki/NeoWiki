<template>
	<div class="statement-editor">
		<div v-if="localStatement" class="statement-editor__fields">
			<div class="statement-editor__field-wrapper">
				<PropertyNameField
					:model-value="localStatement.propertyName.toString()"
					class="statement-editor__property"
					:schema-name="schemaName"
					:can-edit-schema="canEditSchema"
					@edit="$emit( 'edit', statement.propertyName )"
					@delete="$emit( 'remove' )"
				/>
			</div>
			<div class="statement-editor__field-wrapper">
				<component
					:is="componentRegistry.getValueEditingComponent( localStatement.format )"
					:model-value="localStatement.value"
					:property="propertyDefinition"
					class="statement-editor__value"
					@update:model-value="updateStatementValue"
				/>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { Statement } from '@neo/domain/Statement';
import { Value } from '@neo/domain/Value';
import PropertyNameField from '@/components/Editor/PropertyNameField.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import type { PropertyName, PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';
import { type SchemaName } from '@neo/domain/Schema.ts';

// The caller is responsible for providing a PropertyDefinition of the right type matching the statement's property name.
const props = defineProps<{
	statement: Statement;
	schemaName: SchemaName;
	canEditSchema: boolean;
	propertyDefinition: PropertyDefinition;
}>();

const componentRegistry = NeoWikiServices.getComponentRegistry();

const emit = defineEmits( [ 'update', 'remove', 'edit' ] );

const localStatement = ref<Statement>( props.statement );

watch( () => props.statement, ( newStatement ) => {
	localStatement.value = newStatement;
}, { deep: true } );

const updateStatementValue = ( newValue: Value | undefined ): void => {
	localStatement.value = new Statement(
		localStatement.value.propertyName as PropertyName,
		localStatement.value.format,
		newValue
	);
	emit( 'update', localStatement.value );
};
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.statement-editor {
	display: flex;
	width: $size-full;
	padding: $spacing-50 0;
	border-bottom: $border-width-base solid $border-color-subtle;
	transition: background-color $transition-duration-base $transition-timing-function-system;

	&:hover {
		background-color: $background-color-interactive-subtle;
	}

	&__fields {
		display: flex;
		flex-grow: 1;
		gap: $spacing-100;
		align-items: flex-start;
	}

	&__value {
		width: $size-2400;
	}
}

.edit-icon {
	.cdx-icon {
		svg {
			fill: $border-color-interactive !important;
		}
	}
}
</style>
