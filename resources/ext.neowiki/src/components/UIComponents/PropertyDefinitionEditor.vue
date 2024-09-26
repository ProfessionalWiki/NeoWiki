<template>
	<CdxDialog
		v-model:open="isOpen"
		title="New Property"
		class="property-definition-editor"
	>
		<div v-if="localProperty" class="editor-content">
			<div class="inline-fields">
				<NeoTextField
					v-model="localProperty.name"
					label="Property Name"
					:required="true"
				/>
				<div class="field-group format-select">
					<label for="format-select">Format</label>
					<CdxSelect
						v-model:selected="localProperty.format"
						:menu-items="formatOptions"
					/>
				</div>
			</div>
			<NeoTextField
				v-model="localProperty.description"
				label="Description"
			/>
			<div class="required-checkbox">
				<CdxCheckbox
					v-model="localProperty.required"
					label="Required"
				>
					<small>Make it a required field</small>
				</CdxCheckbox>
			</div>
		</div>
		<div v-else class="no-property">
			<p>No property selected</p>
		</div>

		<template #footer>
			<div class="dialog-footer">
				<CdxButton
					class="cancel-button"
					weight="quiet"
					@click="cancel">
					Cancel
				</CdxButton>
				<CdxButton
					class="save-button"
					action="progressive"
					weight="primary"
					:disabled="!localProperty"
					@click="save">
					Add Property
				</CdxButton>
			</div>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxDialog, CdxButton, CdxSelect, CdxCheckbox } from '@wikimedia/codex';
import NeoTextField from '@/components/UIComponents/NeoTextField.vue';
import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';

const props = defineProps<{
	property: PropertyDefinition | null;
}>();

const emit = defineEmits( [ 'save', 'cancel' ] );

const isOpen = ref( false );
const localProperty = ref<PropertyDefinition | null>( null );

watch( () => props.property, ( newProperty ) => {
	localProperty.value = newProperty ? { ...newProperty } : null;
} );

const formatOptions = [
	{ value: 'text', label: 'Text' },
	{ value: 'url', label: 'URL' },
	{ value: 'number', label: 'Number' }
	// Add more format options as needed
];

const openDialog = (): void => {
	isOpen.value = true;
	localProperty.value = props.property ? { ...props.property } : null;
};

const cancel = (): void => {
	isOpen.value = false;
	emit( 'cancel' );
};

const save = (): void => {
	if ( localProperty.value ) {
		isOpen.value = false;
		emit( 'save', { ...localProperty.value } );
	}
};

defineExpose( { openDialog } );
</script>

<style lang="scss">
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

.property-definition-editor {
	max-width: 600px !important;

	:deep( .cdx-dialog__header ) {
		padding: $spacing-100 $spacing-150;
		border-bottom: $border-width-base solid $border-color-base;
	}

	:deep( .cdx-dialog__header__title ) {
		font-size: $font-size-x-large;
		font-weight: $font-weight-bold;
		color: $color-base;
	}

	:deep( .cdx-dialog__body ) {
		padding: $spacing-150;
	}

	.editor-content {
		display: flex;
		flex-direction: column;
		gap: $spacing-100;
	}

	.inline-fields {
		display: flex;
		gap: $spacing-75;
	}

	.cdx-label {
		&__label {
			&__text {
				color: $color-base-fixed;
				font-size: $font-size-small !important;
				font-weight: $font-weight-normal !important;
			}
		}
	}

	.field-group {
		label {
			font-size: $font-size-small;
			font-weight: $font-weight-normal !important;
			color: $color-base-fixed;
		}
	}

	.property-name {
		flex: 1;
	}

	.format-select {
		flex: 1;

		:deep( .cdx-select__handle ) {
			border-radius: $border-radius-base;
			border: $border-width-base solid $border-color-base;
			transition: all $transition-duration-base $transition-timing-function-system;

			&:hover,
			&:focus {
				border-color: $border-color-progressive;
			}
		}
	}

	.required-checkbox {
		margin-top: $spacing-50;

		:deep( .cdx-checkbox__label ) {
			font-size: $font-size-small;
		}
	}

	.no-property {
		display: flex;
		justify-content: center;
		align-items: center;
		height: $size-1600;
		color: $color-base;
		font-style: italic;
	}

	:deep( .cdx-dialog__footer ) {
		padding: $spacing-100 $spacing-150;
		border-top: $border-width-base solid $border-color-base;
	}

	.dialog-footer {
		display: flex;
		justify-content: flex-end;
		gap: $spacing-75;
	}

	.cancel-button {
		color: $color-base;

		&:hover {
			background-color: $background-color-button-quiet--hover;
		}
	}

	.save-button {
		background-color: $background-color-progressive;
		color: $color-inverted;

		&:hover {
			background-color: $background-color-progressive--hover;
		}

		&:disabled {
			opacity: $opacity-icon-base--disabled;
			cursor: $cursor-not-allowed;
		}
	}
}
</style>
