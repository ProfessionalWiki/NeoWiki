<template>
	<li
		:id="subjectRowDomId( subject.getId().text )"
		class="ext-neowiki-subject-row"
		:class="{
			'ext-neowiki-subject-row--emphasized': emphasized,
			'ext-neowiki-subject-row--highlighted': highlighted,
			'ext-neowiki-subject-row--focused': focused,
			'ext-neowiki-subject-row--expanded': expanded
		}"
	>
		<details :open="expanded">
			<summary
				class="ext-neowiki-subject-row__header"
				@click.prevent="emit( 'toggle', subject )"
			>
				<CdxIcon
					class="ext-neowiki-subject-row__chevron"
					:icon="expanded ? cdxIconCollapse : cdxIconExpand"
					size="small"
				/>
				<template v-if="mainSubjectControl === 'demote'">
					<CdxButton
						v-if="canEdit"
						class="ext-neowiki-subject-row__main-indicator"
						weight="quiet"
						:aria-label="$i18n( 'neowiki-managesubjects-row-demote' ).text()"
						:title="$i18n( 'neowiki-managesubjects-row-demote' ).text()"
						@click.stop="emit( 'demote', subject )"
					>
						<CdxIcon :icon="cdxIconPushPin" />
					</CdxButton>
					<CdxIcon
						v-else
						class="ext-neowiki-subject-row__main-indicator"
						:icon="cdxIconPushPin"
						:icon-label="$i18n( 'neowiki-managesubjects-main-subject-indicator' ).text()"
					/>
				</template>
				<span class="ext-neowiki-subject-row__title">
					<span class="ext-neowiki-subject-row__label">
						{{ displayName }}
					</span>
					<span class="ext-neowiki-subject-row__subtitle">
						<SchemaNameDisplay
							v-if="schemaName !== null"
							class="ext-neowiki-subject-row__schema"
							:schema-name="schemaName"
							@click.stop
						/>
						<span class="ext-neowiki-subject-row__count">
							{{ $i18n( 'neowiki-managesubjects-statement-count', statementCount ).text() }}
						</span>
					</span>
				</span>
				<span class="ext-neowiki-subject-row__actions">
					<a
						v-if="subjectPageUrl !== null"
						class="ext-neowiki-subject-row__open cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--weight-quiet"
						:href="subjectPageUrl"
						:aria-label="$i18n( 'neowiki-managesubjects-row-open' ).text()"
						:title="$i18n( 'neowiki-managesubjects-row-open' ).text()"
						@click.stop
					>
						<CdxIcon :icon="cdxIconNext" />
					</a>
					<CdxButton
						weight="quiet"
						:aria-label="$i18n( 'neowiki-managesubjects-row-copy-link' ).text()"
						:title="$i18n( 'neowiki-managesubjects-row-copy-link' ).text()"
						@click.stop="emit( 'copy-link', subject )"
					>
						<CdxIcon :icon="cdxIconLink" />
					</CdxButton>
					<CdxButton
						v-if="canEdit"
						weight="quiet"
						:aria-label="$i18n( 'neowiki-managesubjects-row-edit' ).text()"
						:title="$i18n( 'neowiki-managesubjects-row-edit' ).text()"
						@click.stop="emit( 'edit', subject )"
					>
						<CdxIcon :icon="cdxIconEdit" />
					</CdxButton>
					<CdxButton
						v-if="canEdit && mainSubjectControl === 'promote'"
						weight="quiet"
						:aria-label="$i18n( 'neowiki-managesubjects-row-promote' ).text()"
						:title="$i18n( 'neowiki-managesubjects-row-promote' ).text()"
						@click.stop="emit( 'promote', subject )"
					>
						<CdxIcon :icon="cdxIconPushPin" />
					</CdxButton>
					<CdxButton
						v-if="canMove"
						weight="quiet"
						:aria-label="$i18n( 'neowiki-managesubjects-row-move' ).text()"
						:title="$i18n( 'neowiki-managesubjects-row-move' ).text()"
						@click.stop="emit( 'move', subject )"
					>
						<CdxIcon :icon="cdxIconArticleRedirect" />
					</CdxButton>
					<CdxButton
						v-if="canDelete"
						weight="quiet"
						action="destructive"
						:aria-label="$i18n( 'neowiki-managesubjects-row-delete' ).text()"
						:title="$i18n( 'neowiki-managesubjects-row-delete' ).text()"
						@click.stop="emit( 'delete', subject )"
					>
						<CdxIcon :icon="cdxIconTrash" />
					</CdxButton>
					<span
						v-if="showDragHandle"
						class="ext-neowiki-subject-row__drag-handle"
						:title="$i18n( 'neowiki-managesubjects-row-drag-handle' ).text()"
						@click.stop
					>
						<CdxIcon
							:icon="cdxIconDraggable"
							:aria-hidden="true"
						/>
					</span>
				</span>
				<span
					class="ext-neowiki-subject-row__actions-menu"
					@click.stop
				>
					<CdxMenuButton
						v-model:selected="menuSelection"
						:menu-items="menuItems"
						:aria-label="$i18n( 'neowiki-managesubjects-row-more' ).text()"
						:title="$i18n( 'neowiki-managesubjects-row-more' ).text()"
						@update:selected="dispatchMenuAction"
					>
						<CdxIcon :icon="cdxIconEllipsis" />
					</CdxMenuButton>
				</span>
			</summary>
			<div class="ext-neowiki-subject-row__expanded">
				<SubjectStatementsView :subject="subject" />
				<footer class="ext-neowiki-subject-row__footer">
					<dl class="ext-neowiki-subject-row__identifiers">
						<div class="ext-neowiki-subject-row__id">
							<dt class="ext-neowiki-subject-row__id-label">
								{{ $i18n( 'neowiki-managesubjects-id-label' ).text() }}
							</dt>
							<dd class="ext-neowiki-subject-row__id-value">
								<button
									type="button"
									class="ext-neowiki-subject-row__id-button"
									:title="$i18n( 'neowiki-managesubjects-id-copy', subject.getId().text ).text()"
									:aria-label="$i18n( 'neowiki-managesubjects-id-copy', subject.getId().text ).text()"
									@click="copySubjectId"
								>
									<data :value="subject.getId().text">
										{{ subject.getId().text }}
									</data>
								</button>
							</dd>
						</div>
						<div
							v-if="iri"
							class="ext-neowiki-subject-row__iri"
						>
							<dt class="ext-neowiki-subject-row__iri-label">
								{{ $i18n( 'neowiki-managesubjects-iri-label' ).text() }}
							</dt>
							<dd class="ext-neowiki-subject-row__iri-value">
								<button
									type="button"
									class="ext-neowiki-subject-row__iri-button"
									:title="$i18n( 'neowiki-managesubjects-iri-copy', iri ).text()"
									:aria-label="$i18n( 'neowiki-managesubjects-iri-copy', iri ).text()"
									@click="copySubjectIri"
								>
									<data :value="iri">
										{{ iri }}
									</data>
								</button>
							</dd>
						</div>
						<div
							v-if="page !== null"
							class="ext-neowiki-subject-row__page"
						>
							<dt class="ext-neowiki-subject-row__page-label">
								{{ $i18n( 'neowiki-special-subject-page-label' ).text() }}
							</dt>
							<dd class="ext-neowiki-subject-row__page-value">
								<a :href="pageUrl( page.getPageName() )">{{ page.getPageName() }}</a>
							</dd>
						</div>
					</dl>
					<DataExportButton
						:label="$i18n( 'neowiki-managesubjects-export-button' ).text()"
						:projections="rdfProjections"
						v-bind="exportUrls"
					/>
				</footer>
			</div>
		</details>
	</li>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { CdxButton, CdxIcon, CdxMenuButton } from '@wikimedia/codex';
import type { MenuButtonItemData } from '@wikimedia/codex';
import {
	cdxIconArticleRedirect,
	cdxIconCollapse,
	cdxIconDraggable,
	cdxIconEdit,
	cdxIconEllipsis,
	cdxIconExpand,
	cdxIconLink,
	cdxIconNext,
	cdxIconPushPin,
	cdxIconTrash
} from '@wikimedia/codex-icons';
import { Subject } from '@/domain/Subject.ts';
import type { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { subjectRowDomId } from '@/presentation/subjectRowAnchor.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import { schemaNameToShow } from '@/presentation/schemaNameToShow.ts';
import { subjectExportUrls } from '@/presentation/DataExportMenu.ts';
import { subjectIri } from '@/presentation/subjectIri.ts';
import { copyToClipboard } from '@/presentation/copyToClipboard.ts';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';
import DataExportButton from '@/components/SubjectsManager/DataExportButton.vue';

/**
 * Which Main Subject control the row carries: the pin that demotes the page's Main Subject, the pin
 * that promotes another Subject to it, or none, on a surface where a page's Main Subject is not a
 * thing the row can change.
 */
type MainSubjectControl = 'none' | 'promote' | 'demote';

const props = withDefaults( defineProps<{
	subject: Subject;
	expanded: boolean;
	/**
	 * The Subject's own page, offered as a row action. Null where the reader is on it already.
	 */
	subjectPageUrl?: string | null;
	/**
	 * Gives the row the prominence a surface's focal Subject gets: the page's Main Subject on the
	 * Data tab, the Subject a page is about on Special:Subject. Says nothing about either by itself.
	 */
	emphasized?: boolean;
	/** Arrived at through a deep link. */
	highlighted?: boolean;
	/** Just acted on, so the eye is drawn back to it. */
	focused?: boolean;
	canEdit?: boolean;
	canDelete?: boolean;
	/** Whether this surface offers moving the Subject to another page at all. */
	canMove?: boolean;
	mainSubjectControl?: MainSubjectControl;
	showDragHandle?: boolean;
	/**
	 * The page the Subject is stored on, linked in the expanded footer. Null where naming it says
	 * nothing — the Data tab is that page — or where the read resolved none.
	 */
	page?: PageIdentifiers | null;
}>(), {
	subjectPageUrl: null,
	emphasized: false,
	highlighted: false,
	focused: false,
	canEdit: false,
	canDelete: false,
	canMove: false,
	mainSubjectControl: 'none',
	showDragHandle: false,
	page: null
} );

// Everything the page decides is emitted rather than done here: which Subject a promotion moves
// aside, what a deletion confirms against, where a copied link points. The two clipboard copies
// below are the exceptions — they need nothing but this row's own Subject.
const emit = defineEmits<{
	toggle: [ subject: Subject ];
	edit: [ subject: Subject ];
	promote: [ subject: Subject ];
	demote: [ subject: Subject ];
	move: [ subject: Subject ];
	delete: [ subject: Subject ];
	'copy-link': [ subject: Subject ];
}>();

// The projections the expanded footer's export menu offers, permission-filtered server-side. Set by
// whichever surface mounts the row: the Data tab's action and Special:Subject alike.
const rdfProjections = ( mw.config.get( 'wgNeoWikiRdfProjections' ) as string[] | null ) ?? [];

const displayName = computed( () => subjectDisplayName( props.subject ) );
const schemaName = computed( () => schemaNameToShow( props.subject ) );
const statementCount = computed( () => props.subject.getNamesOfNonEmptyProperties().length );
const exportUrls = computed( () => subjectExportUrls( props.subject.getId().text ) );

/**
 * The Subject's concept URI, shown and copyable in the expanded footer. Empty for a Subject of another
 * Source, which this wiki mints no IRI for, and then not shown at all.
 */
const iri = computed( () => subjectIri( props.subject.getId().text ) );

function pageUrl( pageName: string ): string {
	return mw.util.getUrl( pageName );
}

// Opening and copying a link lead: they change nothing. Edit and promote change the row in place;
// move and delete take it out of the listing, with delete last. Mirrors the inline strip's order.
const menuItems = computed<MenuButtonItemData[]>( () => {
	// Neither is permission-gated: everyone, read-only users included, may reach a Subject's page
	// and copy a link to it.
	const items: MenuButtonItemData[] = [];

	if ( props.subjectPageUrl !== null ) {
		// A url makes Codex render the item as a link, so choosing it navigates by itself.
		items.push( {
			value: 'open',
			label: mw.msg( 'neowiki-managesubjects-row-open' ),
			icon: cdxIconNext,
			url: props.subjectPageUrl
		} );
	}

	items.push( {
		value: 'copy-link',
		label: mw.msg( 'neowiki-managesubjects-row-copy-link' ),
		icon: cdxIconLink
	} );

	if ( props.canEdit ) {
		items.push( {
			value: 'edit',
			label: mw.msg( 'neowiki-managesubjects-row-edit' ),
			icon: cdxIconEdit
		} );

		if ( props.mainSubjectControl === 'promote' ) {
			items.push( {
				value: 'promote',
				label: mw.msg( 'neowiki-managesubjects-row-promote' ),
				icon: cdxIconPushPin
			} );
		}
	}

	if ( props.canMove ) {
		items.push( {
			value: 'move',
			label: mw.msg( 'neowiki-managesubjects-row-move' ),
			icon: cdxIconArticleRedirect
		} );
	}

	if ( props.canDelete ) {
		items.push( {
			value: 'delete',
			label: mw.msg( 'neowiki-managesubjects-row-delete' ),
			icon: cdxIconTrash,
			action: 'destructive'
		} );
	}

	return items;
} );

const menuSelection = ref<string | number | null>( null );

// 'open' is absent: that item carries a url, so Codex navigates without this being asked.
function dispatchMenuAction( value: string | number | null ): void {
	menuSelection.value = null;

	if ( value === 'copy-link' ) {
		emit( 'copy-link', props.subject );
	} else if ( value === 'edit' ) {
		emit( 'edit', props.subject );
	} else if ( value === 'promote' ) {
		emit( 'promote', props.subject );
	} else if ( value === 'move' ) {
		emit( 'move', props.subject );
	} else if ( value === 'delete' ) {
		emit( 'delete', props.subject );
	}
}

function copySubjectId(): Promise<void> {
	const id = props.subject.getId().text;

	return copyToClipboard(
		id,
		mw.msg( 'neowiki-managesubjects-id-copied', id ),
		mw.msg( 'neowiki-managesubjects-id-copy-error' )
	);
}

function copySubjectIri(): Promise<void> {
	return copyToClipboard(
		iri.value,
		mw.msg( 'neowiki-managesubjects-iri-copied', iri.value ),
		mw.msg( 'neowiki-managesubjects-iri-copy-error' )
	);
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-row {
	border: @border-base;
	border-radius: @border-radius-base;
	background: @background-color-base;
	// Baseline zero-color shadow so the focused-state ring can transition in/out smoothly
	// rather than snap between "none" and a value.
	box-shadow: @box-shadow-outset-small transparent;
	transition: @transition-property-base @transition-duration-medium @transition-timing-function-system;

	@media ( prefers-reduced-motion: reduce ) {
		transition-duration: 0s;
	}
	font-size: @font-size-small;
	line-height: 1.375rem; // Codex 2.0+ line-height-small

	&--emphasized {
		border-color: @border-color-progressive;

		.ext-neowiki-subject-row__header {
			background-color: @background-color-progressive-subtle;

			&:hover {
				background-color: @background-color-interactive-subtle;
			}

			&:active {
				background-color: @background-color-interactive;
			}

			.ext-neowiki-subject-row__label {
				color: @color-progressive;
			}
		}
	}

	&--highlighted {
		background: @background-color-progressive-subtle;
	}

	&--focused {
		border-color: @border-color-progressive--focus;
		box-shadow: @box-shadow-outset-small @box-shadow-color-progressive--focus;
		// Transparent 1px outline for Windows high-contrast mode — Codex focus pattern.
		outline: @outline-base--focus;
	}

	&--ghost {
		opacity: 0.5;
		background-color: @background-color-interactive-subtle;
	}

	&__header {
		display: flex;
		align-items: center;
		gap: @spacing-75;
		padding: @spacing-75 @spacing-100;
		cursor: pointer;
		user-select: none;
		list-style: none;
		transition-property: background-color, color, border-color, box-shadow;
		transition-duration: @transition-duration-base;
		transition-timing-function: @transition-timing-function-system;

		&::-webkit-details-marker {
			display: none;
		}

		&:hover {
			background-color: @background-color-interactive-subtle;
		}

		&:active {
			background-color: @background-color-interactive;
		}

		&:focus-visible {
			outline: @outline-base--focus;
			box-shadow: inset 0 0 0 2px @box-shadow-color-progressive--focus;
		}
	}

	&__chevron {
		flex-shrink: 0;
		color: @color-subtle;
	}

	&__main-indicator {
		flex-shrink: 0;

		// Codex sets an explicit `color` on `.cdx-icon`, matching our class's specificity.
		// Chain the class to win the cascade regardless of Codex/bundle load order.
		&.cdx-icon {
			color: @color-progressive;
		}

		// When the user can edit, the indicator renders as a quiet CdxButton so clicking it
		// demotes the subject. Paint the nested icon progressive to match the read-only case.
		&.cdx-button .cdx-icon {
			color: @color-progressive;
		}
	}

	&__title {
		display: flex;
		flex-direction: column;
		gap: @spacing-12;
		flex-grow: 1;
		min-width: 0;
	}

	&__subtitle {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 0 @spacing-50;
		min-width: 0;
		font-size: @font-size-small;
		color: @color-subtle;
	}

	/* The badge ellipsises its own text; the row only has to let it shrink. */
	&__schema {
		min-width: 0;
	}

	&__count {
		white-space: nowrap;
	}

	&__label {
		font-size: @font-size-medium;
		font-weight: @font-weight-bold;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__identifiers {
		display: flex;
		flex-direction: column;
		min-width: 0;
		margin: 0;
		font-size: @font-size-x-small;
		color: @color-subtle;
	}

	&__id,
	&__iri,
	&__page {
		display: flex;
		align-items: baseline;
		gap: @spacing-25;
		min-width: 0;
	}

	&__id-label,
	&__iri-label,
	&__page-label {
		flex-shrink: 0;
	}

	&__id-value,
	&__iri-value,
	&__page-value {
		display: flex;
		min-width: 0;
		margin: 0;
	}

	/* A page title, not an identifier: no monospace, and long ones ellipsize like the IRI. */
	&__page-value a {
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__id-button,
	&__iri-button {
		appearance: none;
		background: transparent;
		border: 0;
		padding: 0;
		cursor: pointer;
		color: inherit;
		font: inherit;
		font-family: @font-family-monospace;
		// The IRI is a full URL: let a long one ellipsize instead of stretching the footer. The whole
		// value stays in the button title and is what the click copies.
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;

		&:hover {
			color: @color-base;
		}
	}

	/* The separator belongs to the pair: a row whose badge is withheld draws none. */
	&__schema + &__count::before {
		content: '•';
		margin-inline-end: @spacing-50;
	}

	&__actions {
		display: inline-flex;
		gap: @spacing-25;
		flex-shrink: 0;

		@media ( max-width: @max-width-breakpoint-mobile ) {
			display: none;
		}

		@media ( min-width: @min-width-breakpoint-tablet ) and ( hover: hover ) {
			opacity: 0;
			transform: translateX( @spacing-50 );
			transition: opacity @transition-duration-medium @transition-timing-function-system, transform @transition-duration-medium @transition-timing-function-system;

			.ext-neowiki-subject-row:hover &,
			.ext-neowiki-subject-row:has( :focus-visible ) &,
			.ext-neowiki-subject-row--highlighted &,
			.ext-neowiki-subject-row--expanded & {
				// :has( :focus-visible ) rather than :focus-within: keyboard focus must reveal the
				// controls a user is tabbing through, but mouse clicks also focus what they hit (a copy
				// button, the summary when toggling a row) and would pin the controls visible after the
				// pointer leaves.
				opacity: 1;
				transform: translateX( 0 );
			}
		}
	}

	/* A link styled as one of the row's quiet icon buttons — Codex's fake-button pattern. */
	&__open.cdx-button {
		min-width: @min-size-interactive-pointer;
		padding: 0;

		&:hover {
			text-decoration: none;
		}
	}

	&__drag-handle {
		// Set off from the buttons: this is grabbed, not clicked, and delete sits right before it.
		margin-inline-start: @spacing-50;
		min-width: @min-size-interactive-pointer;
		min-height: @min-size-interactive-pointer;
		padding-inline: @spacing-30;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		box-sizing: border-box;
		cursor: grab;

		&:active {
			cursor: grabbing;
		}

		.cdx-icon {
			color: @color-placeholder;
		}
	}

	&__actions-menu {
		flex-shrink: 0;

		@media ( min-width: @min-width-breakpoint-tablet ) {
			display: none;
		}

		@media ( max-width: @max-width-breakpoint-mobile ) and ( hover: hover ) {
			opacity: 0;
			transition: opacity @transition-duration-medium @transition-timing-function-system;

			.ext-neowiki-subject-row:hover &,
			.ext-neowiki-subject-row:has( :focus-visible ) &,
			.ext-neowiki-subject-row--highlighted &,
			.ext-neowiki-subject-row--expanded &,
			&:has( [ aria-expanded='true' ] ) {
				// Keyboard-only focus reveal for the same reason as __actions above.
				opacity: 1;
			}
		}
	}

	&__expanded {
		padding: @spacing-100;
		border-top: @border-base;
		background: @background-color-neutral-subtle;
	}

	&__footer {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: space-between;
		gap: @spacing-100;
		margin-top: @spacing-100;
		padding-top: @spacing-75;
		border-top: @border-width-base @border-style-base @border-color-subtle;
	}
}
</style>
