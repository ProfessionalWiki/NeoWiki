<template>
	<CdxField
		class="ext-neowiki-number-display-attributes"
		:status="message === null ? 'default' : 'error'"
		:messages="message === null ? {} : { error: message }"
	>
		<template #label>
			{{ $i18n( 'neowiki-property-editor-precision' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'neowiki-display-attribute-precision-description' ).text() }}
		</template>
		<div
			ref="inputRow"
			class="ext-neowiki-number-display-attributes__input"
		>
			<NeoNativeInput
				:key="resets"
				:model-value="precisionInput"
				input-type="number"
				min="0"
				:placeholder="property.precision?.toString()"
				@update:model-value="updatePrecision"
				@unparseable-change="precisionUnparseable = $event"
			/>
			<CdxButton
				v-if="displayAttributes.precision !== undefined"
				weight="quiet"
				:aria-label="$i18n( 'neowiki-display-attribute-reset' ).text()"
				:title="$i18n( 'neowiki-display-attribute-reset' ).text()"
				@click="resetPrecision"
			>
				<CdxIcon :icon="cdxIconUndo" />
			</CdxButton>
		</div>
		<template #help-text>
			<template v-if="property.precision === undefined">
				{{ $i18n( 'neowiki-display-attribute-precision-as-entered' ).text() }}
			</template>
			<template v-else>
				{{ $i18n( 'neowiki-display-attribute-precision-from-schema', property.precision ).text() }}
			</template>
		</template>
	</CdxField>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { CdxButton, CdxField, CdxIcon } from '@wikimedia/codex';
import { cdxIconUndo } from '@wikimedia/codex-icons';
import type { NumberProperty } from '@/domain/propertyTypes/Number.ts';
import type { DisplayAttributesEditorEmits, DisplayAttributesEditorProps } from './DisplayAttributesEditorContract.ts';
import NeoNativeInput from '@/components/common/NeoNativeInput.vue';
import { focusFirstValueControl } from '@/components/common/valueControlFocus.ts';

type NumberDisplayAttributes = { precision?: number };

const props = defineProps<DisplayAttributesEditorProps<NumberProperty, NumberDisplayAttributes>>();
const emit = defineEmits<DisplayAttributesEditorEmits<NumberDisplayAttributes>>();

const precisionInput = computed( () => props.displayAttributes.precision?.toString() ?? '' );
const precisionError = ref<string | null>( null );
const precisionUnparseable = ref<string | null>( null );
const message = computed( () => precisionUnparseable.value ?? precisionError.value );

// Text the field cannot read stays in it when the value is emptied, so a reset gives it a new field.
const resets = ref( 0 );
const inputRow = ref<HTMLElement | null>( null );

function updatePrecision( text: string ): void {
	if ( text === '' ) {
		precisionError.value = null;
		emit( 'update:displayAttributes', { precision: undefined } );
		return;
	}

	const precision = Number( text );

	if ( precision < 0 ) {
		precisionError.value = mw.message( 'neowiki-property-editor-precision-non-negative' ).text();
		return;
	}

	precisionError.value = null;
	emit( 'update:displayAttributes', { precision } );
}

async function resetPrecision(): Promise<void> {
	precisionUnparseable.value = null;
	resets.value++;
	updatePrecision( '' );

	await nextTick();
	focusFirstValueControl( inputRow.value! );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-number-display-attributes__input {
	display: flex;
	align-items: center;
	gap: @spacing-50;

	.cdx-text-input {
		max-width: @size-800;
	}
}
</style>
