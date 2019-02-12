(function(OC, OCA) {

	if (OCA.Theming) {
		// https://localcloud.icewind.me/apps/theming/img/groupfolders/folder-group.svg?v=34
		OC.MimeType._mimeTypeIcons['dir-group'] = OC.generateUrl('/apps/theming/img/groupfolders/folder-group.svg?v=' + OCA.Theming.cacheBuster);
	} else {
		OC.MimeType._mimeTypeIcons['dir-group'] = OC.imagePath('groupfolders', 'folder-group');
	}

	var ACL_PROPERTIES = {
		PROPERTY_ACL_LIST:	'{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-list',
		PROPERTY_ACL_ENTRY: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl',
		PROPERTY_ACL_MAPPING_TYPE: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-mapping-type',
		PROPERTY_ACL_MAPPING_ID: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-mapping-id',
		PROPERTY_ACL_MASK: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-mask',
		PROPERTY_ACL_PERMISSIONS: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-permissions',
	};
	_.extend(OC.Files.Client, ACL_PROPERTIES);


	OCA.Groupfolders = {};
	OCA.Groupfolders.ShareTabPlugin = {
		attach: function (shareTabView) {
			shareTabView.on('rendered', function() {
				if (this.model && this.model.get('mountType') === 'group') {
					shareTabView.$el.find('.dialogContainer').append(
						$('<div id="groupfolder-sharing">' +
							'<div class="avatar icon-group-white" style="display: inline-block;background-color: var(--color-primary);padding: 16px;"></div>' +
							'Group folders</div>'
						)
					);
					// TODO: bind js app here
				}
			});
		}
	};
	OCA.Groupfolders.FileListPlugin = {
		attach: function (fileList) {
			fileList.filesClient.addFileInfoParser(function(response) {
				var data = {};
				var props = response.propStat[0].properties;
				var acls = props[ACL_PROPERTIES.PROPERTY_ACL_LIST];
				if (!_.isUndefined(acls)) {
					data.acl = [];
					for (var i = 0; i < acls.length; i++) {
						var acl = {};
						for (var ii in acls[i].children) {
							var prop = acls[i].children[ii];
							if (!prop.nodeName) {
								continue;
							}

							var propertyName = prop.nodeName.split(':')[1] || '';
							switch (propertyName) {
								case 'acl-mapping-id':
									acl.mappingId = prop.textContent || prop.text;
									break;
								case 'acl-mapping-type':
									acl.mappingType = prop.textContent || prop.text;
									break;
								case 'acl-mask':
									acl.mask = parseInt(prop.textContent || prop.text, 10);
									break;
								case 'acl-permissions':
									acl.permissions = parseInt(prop.textContent || prop.text, 10);
									break;
								default:
									break;
							}
						}
						data.acl.push(acl);
					}
				}
				return data;
			});

		}
	};
	OC.Plugins.register('OCA.Files.FileList', OCA.Groupfolders.FileListPlugin);
	// Requires https://github.com/nextcloud/server/compare/enhancement/noid/sharing-tab-plugins?expand=1
	OC.Plugins.register('OCA.Sharing.ShareTabView', OCA.Groupfolders.ShareTabPlugin);

})(OC, OCA);
