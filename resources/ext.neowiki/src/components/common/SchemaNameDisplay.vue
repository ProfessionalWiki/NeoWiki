<template>
	<!-- A span where the surface owns the click: a link inside a tree row competes with it. -->
	<component
		:is="props.link === 'none' ? 'span' : 'a'"
		v-if="nameToShow !== null"
		class="ext-neowiki-schema-name"
		:href="props.link === 'none' ? undefined : schemaUrl"
		:target="props.link === 'new-tab' ? '_blank' : undefined"
		:rel="props.link === 'new-tab' ? 'noopener' : undefined"
	>
		<span class="ext-neowiki-schema-name__noun">{{ accessibleName }}</span>
		<span
			class="ext-neowiki-schema-name__text"
			aria-hidden="true"
		>{{ nameToShow }}</span>
	</component>
</template>

<script lang="ts">
/** Where following the link leaves the reader. */
export type SchemaNameLink = 'same-tab' | 'new-tab' | 'none';
</script>

<script setup lang="ts">
import { computed } from 'vue';
import { schemaNameToShow } from '@/presentation/schemaNameToShow.ts';

const props = withDefaults( defineProps<{
	schemaName: string;
	// What the surface already calls the Subject, so the badge can withhold itself rather
	// than repeat it.
	displayName?: string | null;
	link?: SchemaNameLink;
}>(), {
	displayName: null,
	link: 'same-tab'
} );

const nameToShow = computed( (): string | null =>
	schemaNameToShow( props.schemaName, props.displayName )
);

const schemaUrl = computed( (): string => mw.util.getUrl( `Schema:${ props.schemaName }` ) );

// The badge shows the bare name, so the noun reaches a screen reader through this instead;
// the visible name is aria-hidden, or it would be announced twice.
const accessibleName = computed( (): string =>
	mw.message( 'neowiki-schema-label', props.schemaName ).text()
);
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

/* Not a Codex InfoChip: that is a pill at `@font-size-small`, the size of a navigator row's
	own text, and a wall of them down the navigator. Badge and chip stay distinct shapes for
	distinct ideas — the chip means Property Type on the Schema page. */
.ext-neowiki-schema-name {
	display: inline-flex;
	align-items: center;
	box-sizing: @box-sizing-base;
	max-width: @size-full;
	min-height: @size-125;
	border: @border-width-base @border-style-base @background-color-neutral;
	border-radius: @border-radius-base;
	padding: 0 @spacing-35;
	background-color: @background-color-neutral-subtle;
	color: @color-subtle;
	font-size: @font-size-x-small;
	line-height: @line-height-xx-small;

	&__text {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__noun {
		.ext-neowiki-visually-hidden();
	}
}

/* Codex 2.6.2's progressive-action colours. The Codex MediaWiki serves has older values and
	lacks the two subtle hover/active fills, and it defines the properties it does ship, so a
	`var()` fallback would never reach ours — hence redefining them here. `@supports` because a
	custom property holding an unsupported `light-dark()` is invalid at substitution, which would
	leave the badge with no background rather than MediaWiki's older one.

	Remove once MediaWiki 1.47 ships a Codex carrying these values. */
@supports ( color: light-dark( #000, #fff ) ) {
	.ext-neowiki-schema-name {
		--background-color-progressive-subtle: light-dark( #e8eeff, #1b223d );
		--background-color-progressive-subtle--hover: light-dark( #d9e2ff, #233566 );
		--background-color-progressive-subtle--active: light-dark( #b6d4fb, #3056a9 );
		--border-color-progressive--hover: light-dark( #3056a9, #88a3e8 );
		--border-color-progressive--active: light-dark( #233566, #a6bbf5 );
		--color-progressive: light-dark( #36c, #88a3e8 );
		--color-progressive--hover: light-dark( #3056a9, #a6bbf5 );
		--color-progressive--active: light-dark( #233566, #b6d4fb );
	}
}

/* Where it links, the badge is Codex's normal-weight progressive-action button; the resting
	border takes the fill so it reads flat until hovered, without leaving the box model.

	Every state is spelled out because core styles `a:visited` and underlines `a:hover, a:focus`
	at specificity (0,1,1) — a pseudo-class counts as a class — tying with a bare `a.<class>`
	and winning on load order. Only `a.<class>:<state>` at (0,1,2) beats them, and the states
	tie with one another, so source order decides among them too.

	The subtle fills are `var()` calls because Codex 1.14 has no LESS variable for them. */
a.ext-neowiki-schema-name {
	&,
	&:visited,
	&:hover,
	&:focus,
	&:active {
		text-decoration: none;
	}

	&,
	&:visited {
		border-color: @background-color-progressive-subtle;
		background-color: @background-color-progressive-subtle;
		color: @color-progressive;
	}

	&:hover {
		border-color: @border-color-progressive--hover;
		background-color: var( --background-color-progressive-subtle--hover, @background-color-progressive-subtle );
		color: @color-progressive--hover;
	}

	&:active {
		border-color: @border-color-progressive--active;
		background-color: var( --background-color-progressive-subtle--active, @background-color-progressive-subtle );
		color: @color-progressive--active;
	}

	&:focus-visible {
		outline: @border-width-thick solid @outline-color-progressive--focus;
	}
}

</style>
