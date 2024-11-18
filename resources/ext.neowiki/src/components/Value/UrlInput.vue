<template>
	<div class="neo-url-field">
		<label>{{ label }}</label>
		<div
			v-for="( url, index ) in inputValues"
			:key="index"
			class="url-input-wrapper"
		>
			<CdxField
				:status="errors[index] === null ? 'success' : 'error'"
				:messages="errors[index] === null ? {} : { error: errors[index] }"
			>
				<CdxTextInput
					:input-ref="`${index}-${property.name.toString()}-url-input`"
					:model-value="url"
					input-type="url"
					:start-icon="cdxIconLink"
					@update:model-value="value => onInput( value, index )"
				/>
			</CdxField>
			<CdxButton
				v-if="index > 0"
				weight="quiet"
				aria-hidden="false"
				class="delete-button"
				@click="() => removeValue( index )"
			>
				<CdxIcon :icon="cdxIconTrash" />
			</CdxButton>
		</div>
		<CdxButton
			v-if="property.multiple"
			weight="quiet"
			aria-hidden="false"
			class="add-url-button"
			:disabled="hasInvalidField"
			:class="{ 'add-btn-disabled': hasInvalidField }"
			@click="addValue"
		>
			<CdxIcon :icon="cdxIconAdd" />
		</CdxButton>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CdxField, CdxTextInput, CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconLink, cdxIconTrash } from '@wikimedia/codex-icons';
import { newStringValue, StringValue, Value, ValueType } from '@neo/domain/Value';
import { UrlProperty } from '@neo/domain/valueFormats/Url.ts';
import { ValueInputEmits, ValueInputProps } from '@/components/Value/ValueInputContract';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = withDefaults(
	defineProps<ValueInputProps<UrlProperty>>(),
	{
		modelValue: () => newStringValue( '' ),
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const buildInitialInputValues = ( value: Value ): string[] => {
	if ( value.type === ValueType.String ) {
		const strings = ( value as StringValue ).strings;
		return strings.length > 0 ? strings : [ '' ];
	}
	return [ '' ];
};

const inputValues = ref<string[]>( buildInitialInputValues( props.modelValue ) );
const errors = ref<( string | null )[]>( inputValues.value.map( () => null ) );

const hasInvalidField = computed( () =>
	inputValues.value.some( ( value ) => value.trim() === '' ) || errors.value.some( ( error ) => error !== null )
);

const valueFormat = NeoWikiServices.getValueFormatRegistry().getFormat( 'url' );

function validate(): void {
	// First validate the whole array
	const allValues = newStringValue( ...inputValues.value );
	const allErrors = valueFormat.validate( allValues, props.property );
	const hasValidValue = allErrors.length === 0;

	// Reset all errors
	errors.value = inputValues.value.map( () => null );

	// If we have uniqueness errors, show them on the duplicates
	if ( allErrors.some( ( error ) => error.code === 'unique' ) ) {
		const seen = new Set<string>();
		inputValues.value.forEach( ( url, index ) => {
			const trimmed = url.trim();
			if ( trimmed !== '' && seen.has( trimmed ) ) {
				errors.value[ index ] = mw.message( 'neowiki-field-unique' ).text();
			}
			seen.add( trimmed );
		} );
		return;
	}

	// Then validate individual fields, but ignore empty fields if we have valid values
	inputValues.value.forEach( ( url, index ) => {
		if ( hasValidValue && url.trim() === '' ) {
			return;
		}

		const value = newStringValue( url );
		const validationErrors = valueFormat.validate( value, props.property );

		if ( validationErrors.length > 0 ) {
			errors.value[ index ] = mw.message(
				`neowiki-field-${ validationErrors[ 0 ].code }`,
				...( validationErrors[ 0 ].args ?? [] )
			).text();
		}
	} );
}

function onInput( value: string, index: number ): void {
	inputValues.value[ index ] = value;
	emit( 'update:modelValue', newStringValue( ...inputValues.value ) );
	validate();
}

function addValue(): void {
	inputValues.value.push( '' );
	emit( 'update:modelValue', newStringValue( ...inputValues.value ) );
	validate();
}

function removeValue( index: number ): void {
	inputValues.value.splice( index, 1 );
	emit( 'update:modelValue', newStringValue( ...inputValues.value ) );
	validate();
}

watch( () => props.property, validate );
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.neo-url-field {
	label {
		font-weight: bold;
	}

	.url-input-wrapper {
		display: flex;
		align-items: center;
		margin-bottom: 8px;

		.cdx-field {
			flex: 0 0 100%;
		}

		.cdx-text-input {
			&__input {
				padding-left: 36px;
			}

			&__start-icon {
				left: 8px;
				color: $color-subtle;
			}
		}

		.delete-button {
			margin-left: 8px;
			padding: 4px;

			.cdx-icon {
				color: $color-destructive;
			}
		}
	}

	.add-url-button {
		margin-top: 8px;
		float: right;

		.cdx-icon {
			color: $color-success;
		}
	}

	.add-btn-disabled {
		.cdx-icon {
			opacity: 0.35;
			cursor: $cursor-base--disabled;
		}
	}
}
</style>
