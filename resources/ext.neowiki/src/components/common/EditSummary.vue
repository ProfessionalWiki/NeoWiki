<template>
	<div class="ext-neowiki-edit-summary">
		<CdxAccordion @toggle="onAccordionToggle">
			<template #title>
				<span class="ext-neowiki-edit-summary__label">
					{{ $i18n( 'neowiki-edit-summary-label' ).text() }}
					<span class="ext-neowiki-edit-summary__optional-flag">
						{{ $i18n( 'cdx-label-optional-flag' ).text() }}
					</span>
				</span>
			</template>

			<CdxField
				:optional="true"
				:hide-label="true"
			>
				<template #label>
					{{ $i18n( 'neowiki-edit-summary-label' ).text() }}
				</template>

				<CdxTextArea
					ref="textAreaRef"
					v-model="editSummary"
					:placeholder="$i18n( 'neowiki-edit-summary-placeholder' ).text()"
				/>
			</CdxField>
		</CdxAccordion>

		<div
			v-if="props.helpText"
			class="ext-neowiki-edit-summary__help-text"
		>
			{{ props.helpText }}
		</div>

		<div class="ext-neowiki-edit-summary__actions">
			<CdxButton
				action="progressive"
				weight="primary"
				@click="onSaveClick"
			>
				<CdxIcon :icon="cdxIconCheck" />
				{{ props.saveButtonLabel }}
			</CdxButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxField, CdxIcon, CdxTextArea, CdxAccordion } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';

const props = defineProps<{
	helpText: string;
	saveButtonLabel: string;
}>();

const editSummary = ref( '' );
const textAreaRef = ref<InstanceType<typeof CdxTextArea> | null>( null );

const emit = defineEmits<{
	save: [ summary: string ];
}>();

const onAccordionToggle = ( event: Event ): void => {
	const details = event.target as HTMLDetailsElement;
	if ( details.open && textAreaRef.value ) {
		const textarea = textAreaRef.value.$el.querySelector( 'textarea' );
		if ( textarea ) {
			textarea.focus();
		}
	}
};

const onSaveClick = (): void => {
	emit( 'save', editSummary.value );
};

</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.ext-neowiki-edit-summary {
	.cdx-accordion {
		border-bottom: 0;

		> summary {
			margin-top: -$spacing-50;
			margin-inline: -$spacing-50;
		}

		/* Reset the font size from accordion to match CdxField */
		& .cdx-accordion__header,
		&__header__title,
		&__content {
			font-size: inherit;
		}

		&__content {
			padding: 0;
		}
	}

	&__optional-flag {
		color: $color-subtle;
		font-weight: $font-weight-normal;
	}

	&__help-text {
		margin-top: $spacing-50;
		color: $color-subtle;
		line-height: $line-height-xx-small;

		.cdx-accordion:not( [ open ] ) + & {
			margin-top: 0;
		}
	}

	&__actions {
		display: flex;
		margin-top: $spacing-50;
		gap: $spacing-75;

		.cdx-button {
			width: $size-full;
			max-width: $max-width-base;
		}
	}
}
</style>
