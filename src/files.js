(function(OC, OCA) {

	if (OCA.Theming) {
		OC.MimeType._mimeTypeIcons['dir-group'] = OC.generateUrl('/apps/theming/img/groupfolders/folder-group.svg?v=' + OCA.Theming.cacheBuster);
	} else {
		OC.MimeType._mimeTypeIcons['dir-group'] = OC.imagePath('groupfolders', 'folder-group');
	}

	__webpack_nonce__ = btoa(OC.requestToken)
	__webpack_public_path__ = OC.linkTo('groupfolders', 'build/')

	var ShareTabPlugin = {
		attach: function (shareTabView) {
			shareTabView.on('rendered', function() {
				if (this.model && this.model.get('mountType') === 'group') {

					const el = document.createElement('div');
					const container = shareTabView.$el.find('.dialogContainer')[0];
					container.parentNode.insertBefore(el, container.nextSibling);
					el.id = 'groupfolder-sharing';
					import(/* webpackChunkName: "sharing" */'./SharingSidebarApp').then((Module) => {
						const View = Module.default;
						const vm = new View({
							propsData: {
								fileModel: this.model
							}
						}).$mount(el);
					});
				}
			});
		}
	};
	OC.Plugins.register('OCA.Sharing.ShareTabView', ShareTabPlugin);
})(OC, OCA);
