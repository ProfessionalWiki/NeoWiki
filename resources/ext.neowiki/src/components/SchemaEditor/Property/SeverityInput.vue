<template>
	<CdxMenuButton
		class="ext-neowiki-severity-input"
		:selected="severity"
		:menu-items="menuItems"
		:aria-label="label"
		:title="label"
		@update:selected="onSelected"
	>
		<CdxIcon
			class="ext-neowiki-severity-input__icon"
			:class="'ext-neowiki-severity-input__icon--' + severity"
			:icon="ICONS[ severity ]"
			size="small"
		/>
		<CdxIcon
			class="ext-neowiki-severity-input__indicator"
			:icon="cdxIconExpand"
			size="x-small"
		/>
	</CdxMenuButton>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxIcon, CdxMenuButton, type MenuButtonItemData } from '@wikimedia/codex';
import { cdxIconAlert, cdxIconError, cdxIconExpand } from '@wikimedia/codex-icons';
import { isSeverity, type Severity } from '@/domain/Severity';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

/**
 * Picks the severity of one Constraint (ADR 26): a quiet menu button whose face is the current
 * level's icon, opening a menu that names both levels. Sized to sit at the end of a Constraint's
 * label row or checkbox row without making it taller (see the placement helpers in the styles).
 */
const props = defineProps<{
	/** Absent means the default severity, warning. */
	modelValue?: Severity;
	/** The label of the Constraint's own control, for the accessible name and tooltip. */
	constraint: string;
}>();

const emit = defineEmits<{
	'update:modelValue': [ Severity ];
}>();

const ICONS = {
	warning: cdxIconAlert,
	error: cdxIconError
} as const;

const NAMES = {
	warning: 'neowiki-severity-warning',
	error: 'neowiki-severity-error'
} as const;

const severity = computed( (): Severity => props.modelValue ?? 'warning' );

// Several of these controls can sit on one property, so the name says which Constraint.
const label = computed( (): string => mw.message(
	'neowiki-severity-input-label',
	props.constraint,
	mw.message( NAMES[ severity.value ] ).text()
).text() );

// What error means depends on the wiki: only with enforcement on does it block saving.
const errorDescriptionKey = computed( (): string => NeoWikiExtension.getInstance().isValidationEnforced() ?
	'neowiki-severity-error-description-enforced' :
	'neowiki-severity-error-description-not-enforced' );

const menuItems = computed( (): MenuButtonItemData[] => [
	{
		value: 'warning',
		label: mw.message( NAMES.warning ).text(),
		description: mw.message( 'neowiki-severity-warning-description' ).text(),
		icon: ICONS.warning
	},
	{
		value: 'error',
		label: mw.message( NAMES.error ).text(),
		description: mw.message( errorDescriptionKey.value ).text(),
		icon: ICONS.error
	}
] );

function onSelected( value: string | number | null ): void {
	if ( isSeverity( value ) && value !== severity.value ) {
		emit( 'update:modelValue', value );
	}
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

// Height of the trigger's box in the flow. The button keeps Codex's 32px hit area; the
// difference is spread as negative block margins so a 20px checkbox row or a label row
// stays at its normal height instead of growing to 32px.
@ext-neowiki-severity-input-flow-height: @size-125;
@ext-neowiki-severity-input-overhang: ( ( @size-200 - @ext-neowiki-severity-input-flow-height ) / 2 );

.ext-neowiki-severity-input {
	&.cdx-menu-button {
		display: inline-flex;
		flex-shrink: 0;
	}

	// MediaWiki's Codex build renders the trigger as a bare CdxToggleButton (no
	// `cdx-menu-button__toggle` class), so it is addressed as the root's direct child.
	> .cdx-toggle-button {
		gap: @spacing-12;
		margin-block: -@ext-neowiki-severity-input-overhang;
	}

	// The face takes the Codex colour only for error; warning stays neutral so a fully
	// constrained property does not look alarmed. The menu items keep their own colours.
	// Nested under the root to outrank Codex's `.cdx-toggle-button .cdx-icon` inherit rule.
	& &__icon--warning.cdx-icon {
		color: @color-subtle;
	}

	& &__icon--error.cdx-icon {
		color: @color-error;
	}

	// The menu shows both levels in the colours the Subject editor's messages use. The
	// items are the two fixed levels in a fixed order, so position selects them.
	.cdx-menu__listbox > .cdx-menu-item:nth-of-type( 1 ) .cdx-icon {
		color: @color-warning;
	}

	.cdx-menu__listbox > .cdx-menu-item:nth-of-type( 2 ) .cdx-icon {
		color: @color-error;
	}
}

// Placement helper for a CdxField whose label row ends with the input: the field stays intact
// (label semantics included) and the input is pinned to the field's top end, over the label's
// padding, so the row keeps its height. The label reserves room so long text wraps under it.
.cdx-field.ext-neowiki-severity-field {
	position: relative;

	// Room for the trigger (54px: paddings, icon, gap, chevron, borders) plus a little air.
	> .cdx-label {
		padding-inline-end: @size-400;
	}

	> .cdx-field__control > .ext-neowiki-severity-input {
		position: absolute;
		inset-inline-end: 0;
		top: 0;

		> .cdx-toggle-button {
			margin-block: -@ext-neowiki-severity-input-overhang 0;
		}
	}
}

// Placement helper for a hidden-label CdxField holding a checkbox and the input.
.cdx-field.ext-neowiki-severity-row > .cdx-field__control {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: @spacing-50;

	// The checkbox is no longer the field's last child, which is what Codex keys its
	// bottom margin on; drop it so the row stays a single checkbox row high.
	> .cdx-checkbox {
		margin-bottom: 0;
	}
}
</style>
