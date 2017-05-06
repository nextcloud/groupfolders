$(function () {
	if (OCA.Theming) {
		// https://localcloud.icewind.me/apps/theming/img/groupfolders/folder-group.svg?v=34
		OC.MimeType._mimeTypeIcons['dir-group'] = OC.generateUrl('/apps/theming/img/groupfolders/folder-group.svg?v=' + OCA.Theming.cacheBuster);
	} else {
		OC.MimeType._mimeTypeIcons['dir-group'] = OC.imagePath('groupfolders', 'folder-group');
	}
});
