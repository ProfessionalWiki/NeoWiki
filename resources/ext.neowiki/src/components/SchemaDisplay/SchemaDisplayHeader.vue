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
			<a
				v-if="canCreateSubject"
				class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled ext-neowiki-schema-display-header__create-subject"
				:href="createSubjectUrl"
			>
				<CdxIcon :icon="cdxIconAdd" />
				{{ $i18n( 'neowiki-schema-create-subject', schema.getName() ).text() }}
			</a>
			<CdxButton
				v-if="canEditSchema"
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
import { computed } from 'vue';
import { Schema } from '@/domain/Schema.ts';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconEdit } from '@wikimedia/codex-icons';

const props = defineProps( {
	schema: {
		type: Schema,
		required: true
	},
	canEditSchema: {
		type: Boolean,
		required: true
	},
	canCreateSubject: {
		type: Boolean,
		required: true
	}
} );

const createSubjectUrl = computed( (): string =>
	mw.util.getUrl( `Special:CreateSubject/${ props.schema.getName() }` ) );

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

	&__actions {
		display: flex;
		align-items: center;
		gap: @spacing-50;
	}
}
</style>
