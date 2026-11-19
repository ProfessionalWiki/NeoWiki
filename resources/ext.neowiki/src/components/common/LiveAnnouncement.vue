<template>
	<span
		class="ext-neowiki-live-announcement"
		aria-live="polite"
	>{{ message }}</span>
</template>

<script setup lang="ts">
import { nextTick, ref } from 'vue';

const message = ref( '' );

// A live region speaks when its text changes, so it is emptied first: the next message may read as the last.
async function announce( text: string ): Promise<void> {
	message.value = '';
	await nextTick();
	message.value = text;
}

export interface LiveAnnouncementExposes {
	announce( text: string ): Promise<void>;
}

defineExpose<LiveAnnouncementExposes>( { announce } );
</script>

<style lang="less">
@import ( reference ) '@/assets/mixins.less';

.ext-neowiki-live-announcement {
	.ext-neowiki-visually-hidden();
}
</style>
