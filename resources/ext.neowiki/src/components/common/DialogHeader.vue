<template>
	<div class="ext-neowiki-dialog-header">
		<div
			v-if="$slots[ 'start-actions' ]"
			class="ext-neowiki-dialog-header__actions"
		>
			<slot name="start-actions" />
		</div>

		<div class="cdx-dialog__header__title-group">
			<div class="ext-neowiki-dialog-header__title-line">
				<h2 class="cdx-dialog__header__title">
					{{ title }}
				</h2>
				<slot name="metadata" />
			</div>

			<div
				v-if="$slots[ 'supporting-text' ]"
				class="cdx-dialog__header__subtitle"
			>
				<slot name="supporting-text" />
			</div>
		</div>

		<div
			v-if="$slots[ 'end-actions' ]"
			class="ext-neowiki-dialog-header__actions"
		>
			<slot name="end-actions" />
		</div>

		<CdxButton
			class="ext-neowiki-dialog-header__close"
			:class="{ 'ext-neowiki-dialog-header__close--phones-only': !useCloseButton }"
			weight="quiet"
			type="button"
			:aria-label="$i18n( 'cdx-dialog-close-button-label' ).text()"
			@click="emit( 'close' )"
		>
			<CdxIcon :icon="cdxIconClose" />
		</CdxButton>
	</div>
</template>

<script setup lang="ts">
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconClose } from '@wikimedia/codex-icons';

/**
 * The header of every NeoWiki dialog, for CdxDialog's `#header` slot. CdxDialog then names the dialog by its `title`
 * prop, so the dialog's title is passed to both.
 */
defineProps<{
	title: string;
	/** As Codex 2's: without it, Close shows only on phones, where the dialog may cover the screen. */
	useCloseButton?: boolean;
}>();

const emit = defineEmits<{
	close: [];
}>();
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

/* Codex's `--default` header layout, which a slotted header does not get, aligned to the top so that Close stays on
	the title's line as the supporting text wraps. The title's line is as tall as Close, and its letters are trimmed to
	their capitals and baseline, so that they are centred on Close's icon. */
.ext-neowiki-dialog-header {
	display: flex;
	align-items: flex-start;
	column-gap: @spacing-50;
	box-sizing: @box-sizing-base;
	width: @size-full;

	.cdx-dialog__header__title-group {
		min-width: 0;
	}

	&__title-line {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: @spacing-25 @spacing-50;
		min-height: @size-200;

		.cdx-dialog__header__title {
			text-box: trim-both cap alphabetic;
		}
	}

	&__actions {
		display: flex;
	}

	/* Codex's own pull into the padding, so the icon rather than the button lines up with the content's edge. Nested,
		to outrank the margin and display Codex gives every button. Not Codex's close button class: Codex 2.3.3 and later
		hide that from 640px up in a dialog without `useCloseButton`, which this header decides for itself. */
	& &__close {
		margin-inline-end: -@spacing-50;
	}

	& &__close--phones-only {
		@media ( min-width: @min-width-breakpoint-tablet ) {
			display: none;
		}
	}
}
</style>
