# Makefile for building the project

app_name=groupfolders
project_dir=$(CURDIR)/../$(app_name)
build_dir=$(project_dir)/build
appstore_dir=$(build_dir)/appstore
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
webpack=node_modules/.bin/webpack
version+=1.2.0

jssources=$(wildcard js/*) $(wildcard js/*/*) $(wildcard css/*/*)  $(wildcard css/*)
othersources=$(wildcard appinfo/*) $(wildcard css/*/*) $(wildcard controller/*/*) $(wildcard templates/*/*) $(wildcard log/*/*)

all: build/main.js

clean:
	rm -rf $(sign_dir)
	rm -rf $(build_dir)/$(app_name)-$(version).tar.gz
	rm -rf node_modules

node_modules: package.json
	npm install --deps

build/main.js: node_modules $(jssources)
	NODE_ENV=production $(webpack) --colors --display-error-details --config webpack.prod.config.js

.PHONY: watch
watch: node_modules
	node node_modules/.bin/webpack-dev-server --hot --inline --port 3000 --public localcloud.icewind.me:444 --config webpack.dev.config.js

release: appstore create-tag

create-tag:
	git tag -s -a v$(version) -m "Tagging the $(version) release."
	git push origin v$(version)

appstore: clean build/main.js
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude=/docs \
	--exclude=/build/sign \
	--exclude=/l10n/templates \
	--exclude=/l10n/.tx \
	--exclude=/tests \
	--exclude=/.git \
	--exclude=/.github \
	--exclude=/l10n/l10n.pl \
	--exclude=/CONTRIBUTING.md \
	--exclude=/issue_template.md \
	--exclude=/README.md \
	--exclude=/.gitattributes \
	--exclude=/.gitignore \
	--exclude=/.scrutinizer.yml \
	--exclude=/.travis.yml \
	--exclude=/Makefile \
	$(project_dir)/ $(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(version).tar.gz | openssl base64; \
	fi

