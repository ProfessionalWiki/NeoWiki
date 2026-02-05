<template>
	<div class="ext-neowiki-schema-display-header">
		<div class="ext-neowiki-schema-display-header__content">
			<div class="ext-neowiki-schema-display-header__title">
				{{ schema.getName() }}
			</div>
			<div
				v-if="schema.getDescription()"
				class="ext-neowiki-schema-display-header__description"
			>
				{{ schema.getDescription() }}
			</div>
		</div>
		<div class="ext-neowiki-schema-display-header__actions">
			<CdxButton
				v-if="canEdit"
				weight="quiet"
				:aria-label="$i18n( 'neowiki-edit-schema' ).text()"
				@click="emit( 'edit' )"
			>
				<CdxIcon :icon="cdxIconEdit" />
			</CdxButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import { Schema } from '@/domain/Schema.ts';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconEdit } from '@wikimedia/codex-icons';

defineProps( {
	schema: {
		type: Schema,
		required: true
	},
	canEdit: {
		type: Boolean,
		required: true
	}
} );

const emit = defineEmits<{
	edit: [];
}>();
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-display-header {
	display: flex;
	justify-content: space-between;
	gap: @spacing-100;

	&__content {
		line-height: @line-height-xx-small;
	}

	&__title {
		font-size: @font-size-large;
		font-weight: @font-weight-bold;
	}

	&__description {
		color: @color-subtle;
	}
}
</style>
