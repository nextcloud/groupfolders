$(function () {
	OC.Plugins.register('OCA.Files.App', {
		attach: function() {
			if (OCA.Theming) {
				OC.MimeType._mimeTypeIcons['dir-group'] = OC.generateUrl('/apps/theming/img/groupfolders/folder-group.svg?v=' + OCA.Theming.cacheBuster);
			} else {
				OC.MimeType._mimeTypeIcons['dir-group'] = OC.imagePath('groupfolders', 'folder-group');
			}
		}
	});
});
