<?php
// SPDX-FileCopyrightText: 2020 Robin Appelman <robin@icewind.nl>
// SPDX-License-Identifier: AGPL-3.0-or-later
script($_['appId'], ['groupfolders-settings']); ?>
<div id="searchresults" style="display: none"></div>
<div id="groupfolders-wrapper">
	<h2>
		<?php p($l->t('Group folders')); ?>
	</h2>
	<div id="groupfolders-root"/>
</div>
