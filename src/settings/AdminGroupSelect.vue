<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { CLASS_NAME_ADMIN_DELEGATION } from '../Constants.js'
import DelegatedGroupSelect from './DelegatedGroupSelect.vue'
</script>

<template>
	<DelegatedGroupSelect :delegation-class="CLASS_NAME_ADMIN_DELEGATION" />
</template>
