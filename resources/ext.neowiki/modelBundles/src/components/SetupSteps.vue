<template>
	<nav
		class="ext-neowiki-setup-steps"
		:aria-label="$i18n( 'neowiki-bundle-setup-steps' ).text()"
	>
		<ol class="ext-neowiki-setup-steps__list">
			<li
				v-for="( step, index ) in steps"
				:key="step.id"
				class="ext-neowiki-setup-steps__step"
				:class="{
					'ext-neowiki-setup-steps__step--current': index === currentIndex,
					'ext-neowiki-setup-steps__step--done': index < currentIndex
				}"
			>
				<button
					v-if="index < currentIndex"
					type="button"
					class="ext-neowiki-setup-steps__link"
					:disabled="disabled"
					@click="emit( 'go', step.id )"
				>
					<span class="ext-neowiki-setup-steps__number">{{ index + 1 }}</span>
					<span class="ext-neowiki-setup-steps__label">{{ step.label }}</span>
				</button>
				<span
					v-else
					class="ext-neowiki-setup-steps__link"
					:aria-current="index === currentIndex ? 'step' : undefined"
				>
					<span class="ext-neowiki-setup-steps__number">{{ index + 1 }}</span>
					<span class="ext-neowiki-setup-steps__label">{{ step.label }}</span>
				</span>
			</li>
		</ol>
	</nav>
</template>

<script setup lang="ts" generic="Id extends string">
import { computed } from 'vue';

const props = defineProps<{
	steps: readonly { readonly id: Id; readonly label: string }[];
	current: Id;
	/** Whether the completed steps are out of reach for now. */
	disabled: boolean;
}>();

const emit = defineEmits<{
	/** To a completed step. */
	go: [ id: Id ];
}>();

const currentIndex = computed( () => props.steps.findIndex( ( step ) => step.id === props.current ) );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-setup-steps {
	margin-bottom: @spacing-150;

	&__list {
		display: flex;
		flex-wrap: wrap;
		gap: @spacing-50 @spacing-150;
		margin: 0;
		padding: 0;
		list-style: none;
	}

	&__step {
		margin: 0;
		border-bottom: @border-width-thick @border-style-base @border-color-subtle;
		padding-bottom: @spacing-25;
		color: @color-subtle;

		&--current {
			border-bottom-color: @border-color-progressive;
			color: @color-base;
			font-weight: @font-weight-bold;
		}

		&--done {
			border-bottom-color: @border-color-progressive;
		}
	}

	&__link {
		display: inline-flex;
		align-items: center;
		gap: @spacing-35;
		border: 0;
		padding: 0;
		background: none;
		color: inherit;
		font: inherit;
	}

	button&__link {
		cursor: pointer;
		color: @color-progressive;

		&:hover {
			text-decoration: underline;
		}

		&:focus-visible {
			outline: @border-width-thick @border-style-base @outline-color-progressive--focus;
			outline-offset: @spacing-12;
		}

		&:disabled {
			cursor: default;
			color: @color-disabled;
			text-decoration: none;
		}
	}

	&__number {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: @size-125;
		height: @size-125;
		border-radius: @border-radius-circle;
		background-color: @background-color-interactive-subtle;
		font-size: @font-size-small;
	}

	&__step--current &__number,
	&__step--done &__number {
		background-color: @background-color-progressive;
		color: @color-inverted;
	}
}
</style>
