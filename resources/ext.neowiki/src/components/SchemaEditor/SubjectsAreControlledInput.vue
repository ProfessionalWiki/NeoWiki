<template>
	<CdxField
		class="ext-neowiki-subjects-are-controlled-input"
		:hide-label="true"
	>
		<div class="ext-neowiki-subjects-are-controlled-input__option">
			<CdxCheckbox
				:model-value="modelValue"
				:disabled="!canEditControlledSubjects"
				@update:model-value="emit( 'update:modelValue', $event )"
			>
				{{ $i18n( 'neowiki-schema-editor-controlled' ).text() }}
				<template #description>
					{{ $i18n( canEditControlledSubjects ?
						'neowiki-schema-editor-controlled-help' :
						'neowiki-schema-editor-controlled-denied' ).text() }}
				</template>
			</CdxCheckbox>
			<!-- Outside the checkbox's label, which would take its name into the checkbox's own. A toggle rather
				than a tooltip: a tap shows it too, and Escape is left to the dialog. -->
			<CdxButton
				weight="quiet"
				:aria-label="$i18n( 'neowiki-schema-editor-controlled-info-label' ).text()"
				:title="$i18n( 'neowiki-schema-editor-controlled-info-label' ).text()"
				:aria-expanded="explanationShown"
				@click="explanationShown = !explanationShown"
			>
				<CdxIcon :icon="cdxIconInfo" />
			</CdxButton>
		</div>
		<p
			v-if="explanationShown"
			class="ext-neowiki-subjects-are-controlled-input__explanation"
		>
			{{ $i18n( 'neowiki-schema-editor-controlled-info' ).text() }}
		</p>
	</CdxField>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxCheckbox, CdxField, CdxIcon } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import { useControlledSubjectRight } from '@/composables/useControlledSubjectRight.ts';

defineProps<{
	modelValue: boolean;
}>();

const emit = defineEmits<{
	'update:modelValue': [ value: boolean ];
}>();

const explanationShown = ref( false );
const { canEditControlledSubjects } = useControlledSubjectRight();
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subjects-are-controlled-input {
	/* The button ends the line under the checkbox, and the explanation opens below that line. On the baseline rather
		than at the end: the checkbox's margin and padding reach below its last line. */
	&__option {
		display: flex;
		align-items: last baseline;
		gap: @spacing-25;
	}

	/* In line with the checkbox's own text, past its box. */
	&__explanation {
		margin: @spacing-25 0 0;
		padding-inline-start: calc( @size-125 + @spacing-50 );
		color: @color-subtle;
	}
}
</style>
