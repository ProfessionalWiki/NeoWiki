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
				v-if="view.languageName !== null"
				class="ext-neowiki-monolingual-text-display__language"
				:title="view.languageName"
			>
				<span aria-hidden="true">{{ view.tag }}</span>
				<!-- The space keeps a screen reader from running the name into the text before it; a
					non-breaking one, since the template compiler drops a plain one at an element's start. -->
				<span class="ext-neowiki-monolingual-text-display__language-name">&nbsp;{{ view.languageName }}</span>
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
import { languageName, partsForReader, readerLanguageTags, userLanguageTag } from '@/presentation/mediaWikiLanguages.ts';

interface PartView {
	part: MonolingualText;
	/**
	 * The part's tag in capitals, the way the editor writes it. In script rather than by
	 * `text-transform`, which follows the page's language and turns `it` into `İT` on a Turkish page.
	 */
	tag: string;
	/** The part's language, named for anyone who cannot place its tag; null when the reader reads it. */
	languageName: string | null;
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
	return {
		part: part,
		tag: part.language.toUpperCase(),
		languageName: part.language === userLanguageTag() ? null : languageName( part.language )
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
		until the reader asks for more, and the aside is smaller than the value it follows. The
		states are Codex's link mixin's, without its rule for a trailing icon: that styles an
		external-link icon at the size of body text, where this is a chevron beside small text. */
	&__toggle {
		display: inline-flex;
		align-items: center;
		gap: @spacing-25;
		margin: 0;
		border: 0;
		border-radius: @border-radius-base;
		padding: 0;
		background-color: transparent;
		color: @color-progressive;
		font-family: inherit;
		font-size: @font-size-x-small;
		line-height: inherit;
		cursor: pointer;

		&:hover {
			color: @color-progressive--hover;
			text-decoration: @text-decoration-underline;
		}

		&:active {
			color: @color-progressive--active;
			text-decoration: @text-decoration-underline;
		}

		&:focus-visible {
			outline: @border-style-base @border-width-thick @outline-color-progressive--focus;
		}

		.cdx-icon {
			color: inherit;
		}
	}
}
</style>
