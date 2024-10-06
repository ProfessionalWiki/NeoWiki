<template>
	<CdxDialog
		v-model:open="isOpen"
		:use-close-button="true"
		:title="editMode ? $i18n( 'neowiki-infobox-editor-dialog-title-edit', property?.name.toString() ).text() : $i18n( 'neowiki-infobox-editor-add-property' ).text()"
		class="property-definition-editor"
	>
		<div v-if="localProperty" class="editor-content">
			<div class="inline-fields">
				<NeoTextField
					:model-value="localProperty.name.toString()"
					:label="$i18n( 'neowiki-infobox-editor-property-label' ).text()"
					:required="true"
					@update:model-value="updateForm( 'name', $event as never )"
				/>
				<div class="field-group format-select">
					<label for="format-select">{{ $i18n( 'neowiki-infobox-type' ).text() }}</label>
					<CdxSelect
						v-model:selected="localProperty.format"
						:menu-items="formatOptions"
						@update:selected="updateForm( 'format', $event as never )"
					/>
				</div>
			</div>
			<NeoTextField
				v-model="localProperty.description"
				class="property-description"
				label="Description"
				@update:model-value="updateForm( 'description', $event as never )"
			/>
			<div class="required-checkbox">
				<CdxCheckbox
					v-model="localProperty.required"
					class="neo-round-checkbox"
					:label="$i18n( 'neowiki-field-required' ).text()"
					@update:model-value="updateForm( 'required', $event as never )"
				>
					<small>{{ $i18n( 'neowiki-field-required' ).text() }}</small>
				</CdxCheckbox>
			</div>
		</div>
		<div v-else class="no-property">
			<p>{{ $i18n( 'neowiki-infobox-editor-no-property-selected' ).text() }}</p>
		</div>

		<template #footer>
			<div class="dialog-footer">
				<CdxButton
					class="cancel-button neo-button"
					weight="quiet"
					@click="cancel">
					{{ $i18n( 'neowiki-create-subject-dialog-go-back' ).text() }}
				</CdxButton>
				<CdxButton
					size="medium"
					class="save-button neo-button"
					action="progressive"
					weight="primary"
					:disabled="!localProperty"
					@click="save">
					{{ $i18n( 'neowiki-infobox-editor-save-property' ).text() }}
				</CdxButton>
			</div>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { PropType, ref, watch } from 'vue';
import { CdxDialog, CdxButton, CdxSelect, CdxCheckbox } from '@wikimedia/codex';
import NeoTextField from '@/components/UIComponents/NeoTextField.vue';
import { PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition';

const props = defineProps( {
	property: {
		type: Object as PropType<PropertyDefinition | null>,
		required: true
	},
	editMode: {
		type: Boolean,
		default: false
	}
} );

const emit = defineEmits( [ 'cancel', 'save' ] );

const isOpen = ref( false );
const localProperty = ref<PropertyDefinition | null>( null );

const updateForm = ( field: string, value: never ): void => {
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
	localProperty.value = newProperty ? Object.assign( {}, newProperty ) : null;
}, { deep: true, immediate: true } );

const formatOptions = [
	{ value: 'text', label: mw.message( 'neowiki-infobox-editor-format-text' ).text() },
	{ value: 'url', label: mw.message( 'neowiki-infobox-editor-format-url' ).text() },
	{ value: 'number', label: mw.message( 'neowiki-infobox-editor-format-number' ).text() }
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
		emit( 'save', Object.assign( {}, localProperty.value ) as PropertyDefinition );
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

	.cdx-menu {
		border-radius: 5px !important;
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
