<template>
	<div class="ext-neowiki-dialog-footer">
		<CdxField
			:optional="true"
		>
			<template #label>
				{{ $i18n( 'neowiki-edit-summary-label' ).text() }}
			</template>
			<CdxTextArea
				v-model="editSummary"
				:placeholder="$i18n( 'neowiki-edit-summary-placeholder' ).text()"
			/>
			<template #help-text>
				{{ $i18n( 'neowiki-edit-summary-help-text-subject' ).text() }}
			</template>
		</CdxField>
		<div class="ext-neowiki-dialog-footer__actions">
			<CdxButton
				action="progressive"
				weight="primary"
				@click="onSaveClick"
			>
				<CdxIcon :icon="cdxIconCheck" />
				{{ $i18n( 'neowiki-subject-editor-save' ).text() }}
			</CdxButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxField, CdxIcon, CdxTextArea } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';

const editSummary = ref( '' );

const emit = defineEmits<{
	save: [ summary: string ];
}>();

const onSaveClick = (): void => {
	emit( 'save', editSummary.value );
};

</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.ext-neowiki-dialog-footer {
	display: flex;
	flex-direction: column;
	gap: $spacing-50;

	&__actions {
		display: flex;
		gap: $spacing-75;

		.cdx-button {
			width: $size-full;
			max-width: $max-width-base;
		}
	}
}
</style>
