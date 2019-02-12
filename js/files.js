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

	// Allow nested properties in PROPPATCH
	// WIP branch at https://github.com/juliushaertl/davclient.js/tree/enhancement/nested-proppatch
	var patchClientForNestedPropPatch = function (client) {
		client._client.getPropertyBody = function(key, propValue) {
			var property = this.parseClarkNotation(key);
			var propName;

			if (this.xmlNamespaces[property.namespace]) {
				propName = this.xmlNamespaces[property.namespace] + ':' + property.name;
			} else {
				propName = 'x:' + property.name + ' xmlns:x="' + property.namespace + '"';
			}

			if (Array.isArray(propValue)) {
				var body = '';
				for(var ii in propValue) {
					if ( propValue[ii].hasOwnProperty('type') && propValue[ii].hasOwnProperty('data') ) {
						body += this.getPropertyBody(propValue[ii].type, propValue[ii].data);
					} else {
						body += this.getPropertyBody(ii, propValue[ii]);
					}
				}
				return '      <' + propName + '>' + body + '</' + propName + '>';
			} else if (typeof propValue === 'object') {
				var body = '';
				if ( propValue.hasOwnProperty('type') && propValue.hasOwnProperty('data') ) {
					return this.getPropertyBody(propValue.type, propValue.data)
				}
				for(var ii in propValue) {
					body += this.getPropertyBody(ii, propValue[ii]);
				}
				return '      <' + propName + '>' + body + '</' + propName + '>';
			} else {
				// FIXME: hard-coded for now until we allow properties to
				// specify whether to be escaped or not
				if (propName !== 'd:resourcetype') {
					propValue = dav._escapeXml('' + propValue);
				}

				return '      <' + propName + '>' + propValue + '</' + propName + '>';
			}
		}
		client._client._renderPropSet = function(properties) {
			var body = '  <d:set>\n' +
				'   <d:prop>\n';

			for(var ii in properties) {
				if (!properties.hasOwnProperty(ii)) {
					continue;
				}

				body += this.getPropertyBody(ii, properties[ii])
			}
			body +='    </d:prop>\n';
			body +='  </d:set>\n';
			return body;
		}
	};

	// dummy acl data for testing
	var getDummyAcl = function() {
		var acl2 = {};
		acl2[ACL_PROPERTIES.PROPERTY_ACL_MAPPING_TYPE] = 'group';
		acl2[ACL_PROPERTIES.PROPERTY_ACL_MAPPING_ID] = 'admin';
		acl2[ACL_PROPERTIES.PROPERTY_ACL_MASK] = 31;
		acl2[ACL_PROPERTIES.PROPERTY_ACL_PERMISSIONS] = 0;
		var acl1 = {};
		acl1[ACL_PROPERTIES.PROPERTY_ACL_MAPPING_TYPE] = 'user';
		acl1[ACL_PROPERTIES.PROPERTY_ACL_MAPPING_ID] = 'admin';
		acl1[ACL_PROPERTIES.PROPERTY_ACL_MASK] = 31;
		acl1[ACL_PROPERTIES.PROPERTY_ACL_PERMISSIONS] = 1;

		var acls = [{type: ACL_PROPERTIES.PROPERTY_ACL_ENTRY, data: acl1}, {type: ACL_PROPERTIES.PROPERTY_ACL_ENTRY, data: acl2}];
		var props = {};
		props[OC.Files.Client.PROPERTY_ACL_LIST] = acls;
		return props;
	}

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

			// PROPPATCH/PROPGET testing
			var client = fileList.filesClient;
			patchClientForNestedPropPatch(client);
			// example prop patch
			//client._client.propPatch(client._client.baseUrl + '/Test', getDummyAcl()).then((response) => console.log(response))
			// example fetching acl
			//client.getFileInfo('/Test', {
			//	properties: [OC.Files.Client.PROPERTY_ACL_LIST]
			//} ).then((status, fileInfo) => { console.log(fileInfo); });


		}
	};
	OC.Plugins.register('OCA.Files.FileList', OCA.Groupfolders.FileListPlugin);
	// Requires https://github.com/nextcloud/server/compare/enhancement/noid/sharing-tab-plugins?expand=1
	OC.Plugins.register('OCA.Sharing.ShareTabView', OCA.Groupfolders.ShareTabPlugin);

})(OC, OCA);
