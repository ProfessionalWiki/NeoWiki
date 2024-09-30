<template>
	<CdxDialog
		v-model:open="isOpen"
		:use-close-button="true"
		title="New Property"
		class="property-definition-editor"
	>
		<div v-if="localProperty" class="editor-content">
			<div class="inline-fields">
				<NeoTextField
					:model-value="localProperty.name.toString()"
					label="Property Name"
					:required="true"
					@update:model-value="updateForm( 'name', $event )"
				/>
				<div class="field-group format-select">
					<label for="format-select">Format</label>
					<CdxSelect
						v-model:selected="localProperty.format"
						:menu-items="formatOptions"
						@update:selected="updateForm( 'format', $event )"
					/>
				</div>
			</div>
			<NeoTextField
				v-model="localProperty.description"
				class="property-description"
				label="Description"
				@update:model-value="updateForm( 'description', $event )"
			/>
			<div class="required-checkbox">
				<CdxCheckbox
					v-model="localProperty.required"
					class="neo-round-checkbox"
					label="Required"
					@update:model-value="updateForm( 'required', $event )"
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
					class="cancel-button neo-button"
					weight="quiet"
					@click="cancel">
					Cancel
				</CdxButton>
				<CdxButton
					size="medium"
					class="save-button neo-button"
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
import { PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition';

const props = defineProps<{
	property: PropertyDefinition | null;
}>();

const emit = defineEmits( [ 'cancel', 'save' ] );

const isOpen = ref( false );
const localProperty = ref<PropertyDefinition | null>( null );

const updateForm = ( field: string, value: any ): void => {
	if ( localProperty.value ) {
		if ( field === 'name' ) {
			localProperty.value = {
				...localProperty.value,
				name: new PropertyName( value )
			};
		} else {
			localProperty.value = {
				...localProperty.value,
				[ field ]: value
			};
		}
	}
};

watch( () => props.property, ( newProperty ) => {
	localProperty.value = newProperty ? { ...newProperty } : null;
}, { deep: true, immediate: true } );

const formatOptions = [
	{ value: 'text', label: 'Text' },
	{ value: 'url', label: 'URL' },
	{ value: 'number', label: 'Number' }
];

const openDialog = (): void => {
	isOpen.value = true;
};

const cancel = (): void => {
	isOpen.value = false;
	emit( 'cancel' );
};

const save = (): void => {
	if ( localProperty.value ) {
		isOpen.value = false;
		emit( 'save', localProperty.value as PropertyDefinition );
	}
	console.log( localProperty.value );
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
		font-weight: $font-weight-semi-bold !important;
		color: $color-base-fixed !important;
	}

	.field-group {
		label {
			font-size: $font-size-small;
			font-weight: $font-weight-semi-bold !important;
			color: $color-base-fixed !important;
		}
	}

	.property-name {
		flex: 1;
	}

	.format-select {
		flex: 1;
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

.property-description {
	.cdx-text-input,
	.cdx-text-area {
		border: none !important;
		border-bottom: $border-width-base solid $border-color-base !important;
		box-shadow: none !important;

		&__input,
		&__textarea {
			border: none !important;

			&:focus,
			&:hover {
				border: none !important;
				box-shadow: none !important;
			}
		}

		&:focus-within,
		&:hover {
			border-bottom-color: $border-color-progressive !important;
		}
	}
}
</style>
