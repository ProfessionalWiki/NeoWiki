<template>
	<div class="ext-neowiki-monolingual-text-display">
		<div
			v-for="( view, index ) in shownParts"
			:key="index"
			class="ext-neowiki-monolingual-text-display__part"
		>
			<!-- The text carries the language it is in, so a screen reader reads it in that language and
				`:lang()` font rules match; `dir="auto"` keeps a right-to-left text and the tag after it in
				the right order. -->
			<span
				:lang="view.part.language"
				dir="auto"
			>{{ view.part.text }}</span>
			<span
				v-if="view.tag !== null"
				class="ext-neowiki-monolingual-text-display__language"
				:title="view.languageName"
			>
				<!-- The tag is hidden from a screen reader only when the name beside it says the same
					thing. Unnamed, the tag is all there is to announce. -->
				<span :aria-hidden="view.languageName === undefined ? undefined : 'true'">{{ view.tag }}</span>
				<!-- The space keeps a screen reader from running the name into the text before it; a
					non-breaking one, since the template compiler drops a plain one at an element's start. -->
				<span
					v-if="view.languageName !== undefined"
					class="ext-neowiki-monolingual-text-display__language-name"
				>&nbsp;{{ view.languageName }}</span>
			</span>
		</div>
		<!-- After every part it reveals, so opening it never leaves it between the parts. -->
		<button
			v-if="otherParts.length > 0"
			type="button"
			class="ext-neowiki-monolingual-text-display__toggle"
			:aria-expanded="showingOthers"
			@click="showingOthers = !showingOthers"
		>
			{{ toggleLabel }}
			<CdxIcon
				:icon="showingOthers ? cdxIconCollapse : cdxIconExpand"
				size="x-small"
			/>
		</button>
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { CdxIcon } from '@wikimedia/codex';
import { cdxIconCollapse, cdxIconExpand } from '@wikimedia/codex-icons';
import { type MonolingualText, ValueType } from '@/domain/Value.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import { MonolingualTextProperty } from '@/domain/propertyTypes/MonolingualText.ts';
import {
	languageName,
	partsForReader,
	readerLanguageTags,
	shownLanguageTag,
	userLanguageTag
} from '@/presentation/mediaWikiLanguages.ts';

interface PartView {
	part: MonolingualText;
	/** The part's tag, the way the editor writes it; null when the reader reads that language. */
	tag: string | null;
	/** The part's language, named for anyone who cannot place its tag, when MediaWiki names it. */
	languageName?: string;
}

const props = defineProps<ValueDisplayProps<MonolingualTextProperty>>();

const showingOthers = ref( false );

const parts = computed<MonolingualText[]>(
	() => props.value.type === ValueType.MonolingualText ? props.value.parts : []
);

const readParts = computed<MonolingualText[]>( () => partsForReader( parts.value, readerLanguageTags() ) );

const otherParts = computed<MonolingualText[]>(
	() => parts.value.filter( ( part ) => !readParts.value.includes( part ) )
);

// The parts on show: the ones picked for the reader, then, once asked for, the others.
const shownParts = computed<PartView[]>( () => [
	...readParts.value,
	...( showingOthers.value ? otherParts.value : [] )
].map( viewOf ) );

/**
 * A part is tagged with its language unless it is in the one the interface is in, which the reader
 * can see for themselves.
 */
function viewOf( part: MonolingualText ): PartView {
	if ( part.language === userLanguageTag() ) {
		return { part: part, tag: null };
	}

	return {
		part: part,
		tag: shownLanguageTag( part.language ),
		languageName: languageName( part.language )
	};
}

// Counted in languages, as the link says: two names in one language are one language more.
const toggleLabel = computed<string>( () => showingOthers.value ?
	mw.message( 'neowiki-monolingual-text-fewer-languages' ).text() :
	mw.message(
		'neowiki-monolingual-text-more-languages',
		new Set( otherParts.value.map( ( part ) => part.language ) ).size
	).text()
);
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@wikimedia/codex/mixins/link.less';
@import ( reference ) '@/assets/mixins.less';

.ext-neowiki-monolingual-text-display {
	// The tag written the way the editor shows it, and set back from the text it qualifies.
	&__language {
		margin-inline-start: @spacing-35;
		color: @color-subtle;
		font-size: @font-size-x-small;
		white-space: nowrap;
	}

	&__language-name {
		.ext-neowiki-visually-hidden();
	}

	/* A link rather than a control with a box of its own, so the value reads like any other
		until the reader asks for more, and the aside is smaller than the value it follows. */
	&__toggle {
		.cdx-mixin-link-base();
		display: inline-flex;
		align-items: center;
		gap: @spacing-25;
		margin: 0;
		border: 0;
		padding: 0;
		background-color: transparent;
		font-family: inherit;
		font-size: @font-size-x-small;
		line-height: inherit;
		cursor: pointer;

		/* Codex's link mixin sizes a trailing icon for body text, where this one is the chevron of
			a line of small text, set off by the gap above rather than by padding. */
		.cdx-icon:last-child {
			width: @size-icon-x-small;
			height: @size-icon-x-small;
			padding-left: 0;
			color: inherit;
		}
	}
}
</style>
