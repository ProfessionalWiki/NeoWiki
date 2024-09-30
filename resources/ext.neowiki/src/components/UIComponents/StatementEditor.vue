<template>
	<div class="statement-editor">
		<div v-if="localStatement" class="statement-editor__fields">
			<div class="statement-editor__field-wrapper">
				<NeoTextField
					:model-value="localStatement.propertyName.toString()"
					:required="true"
					:label="$i18n( 'neowiki-infobox-editor-property-label' ).text()"
					class="statement-editor__property"
					@update:model-value="updatePropertyName"
				/>
			</div>
			<div class="statement-editor__field-wrapper">
				<component
					:is="componentRegistry.getValueEditingComponent( localStatement.format )"
					:model-value="getStatementValue( localStatement.value )"
					:label="$i18n( 'neowiki-infobox-editor-value-label' ).text()"
					class="statement-editor__value"
					@validation="handleValidation"
					@update:model-value="updateStatementValue"
				/>
			</div>
		</div>
		<div class="statement-editor__actions">
			<CdxButton
				action="progressive"
				weight="quiet"
				class="statement-editor__action edit-icon"
				@click="$emit( 'edit', statement.propertyName )"
			>
				<CdxIcon :icon="cdxIconEdit" />
			</CdxButton>
			<CdxButton
				action="destructive"
				weight="quiet"
				class="statement-editor__action"
				@click="$emit( 'remove' )"
			>
				<CdxIcon :icon="cdxIconTrash" />
			</CdxButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, watch, Component } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconTrash, cdxIconEdit } from '@wikimedia/codex-icons';
import NeoTextField from '@/components/UIComponents/NeoTextField.vue';
import { Statement } from '@neo/domain/Statement';
import { Value, ValueType, StringValue, NumberValue, newStringValue, newNumberValue } from '@neo/domain/Value';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { FormatSpecificComponentRegistry } from '@/FormatSpecificComponentRegistry.ts';

const props = defineProps<{
	statement: Statement;
	componentRegistry: FormatSpecificComponentRegistry;
}>();

const emit = defineEmits( [ 'update', 'remove', 'edit' ] );

const localStatement = ref<Statement>( props.statement );

watch( () => props.statement, ( newStatement ) => {
	localStatement.value = newStatement;
}, { deep: true } );

const getStatementValue = ( value: Value | undefined ): string => { // TODO: use value format plugin system
	if ( !value ) {
		return '';
	}
	if ( value.type === ValueType.String ) {
		return ( value as StringValue ).strings[ 0 ] || '';
	}
	if ( value.type === ValueType.Number ) {
		return ( value as NumberValue ).number.toString();
	}
	return '';
};

const updatePropertyName = ( newName: string ): void => {
	localStatement.value = new Statement(
		new PropertyName( newName ),
		localStatement.value.format,
		localStatement.value.value
	);
	emit( 'update', localStatement.value );
};

const updateStatementValue = ( newValue: string ): void => {
	let updatedValue: Value;
	switch ( localStatement.value.format ) { // TODO: use value format plugin system
		case 'text':
		case 'url':
			updatedValue = newStringValue( newValue );
			break;
		case 'number':
			updatedValue = newNumberValue( Number( newValue ) );
			break;
		default:
			updatedValue = newStringValue( newValue );
	}
	localStatement.value = new Statement(
		localStatement.value.propertyName as PropertyName,
		localStatement.value.format,
		updatedValue
	);
	emit( 'update', localStatement.value );
};

const handleValidation = ( isValid: boolean ): void => {
	console.log( isValid );
};
</script>

<style lang="scss">
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

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

	&__field-wrapper {
		display: flex;
		flex-direction: column;

		:deep( .cdx-text-input__input ),
		:deep( .cdx-select__handle ) {
			border: $border-width-base solid $border-color-subtle;
			background-color: $background-color-transparent;
			border-radius: $border-radius-base;
			transition: border-color $transition-duration-base $transition-timing-function-system, background-color $transition-duration-base $transition-timing-function-system;

			&:hover,
			&:focus {
				border-color: $border-color-interactive;
				background-color: $background-color-interactive-subtle;
			}
		}

		.cdx-label {
			font-weight: $font-weight-semi-bold !important;
			color: $color-base-fixed !important;
		}

		:deep( .cdx-message ) {
			margin-top: $spacing-25 !important;
		}
	}

	&__property {
		width: $size-full;
	}

	&__value {
		width: $size-full;
	}

	&__actions {
		display: flex;
		gap: $spacing-50;
		align-self: flex-start;
		margin-top: $spacing-150; // Adjust this value to align with the input fields
	}

	&__action {
		padding: $spacing-25;
		min-height: $size-200;
		min-width: $size-200;
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
