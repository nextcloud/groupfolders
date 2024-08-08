# Makefile for building the project
#
# SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
app_name=groupfolders
project_dir=$(CURDIR)/../$(app_name)
build_dir=$(project_dir)/build
appstore_dir=$(build_dir)/appstore
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
webpack=node_modules/.bin/webpack
version+=8.0.0

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
	npm run build

.PHONY: watch
watch: node_modules
	$(webpack) serve --hot --port 3000 --public localcloud.icewind.me:444 --config webpack.dev.config.js

release: appstore create-tag

create-tag:
	git tag -s -a v$(version) -m "Tagging the $(version) release."
	git push origin v$(version)

appstore: clean build/main.js
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude=/.babelrc.js \
	--exclude=/.drone.yml \
	--exclude=/.git \
	--exclude=/.gitattributes \
	--exclude=/.github \
	--exclude=/.gitignore \
	--exclude=/.php_cs.dist \
	--exclude=/.scrutinizer.yml \
	--exclude=/.travis.yml \
	--exclude=/.tx \
	--exclude=/CONTRIBUTING.md \
	--exclude=/Makefile \
	--exclude=/README.md \
	--exclude=/build/sign \
	--exclude=/composer.json \
	--exclude=/composer.lock \
	--exclude=/docs \
	--exclude=/issue_template.md \
	--exclude=/l10n/l10n.pl \
	--exclude=/node_modules \
	--exclude=/package-lock.json \
	--exclude=/package.json \
	--exclude=/postcss.config.js \
	--exclude=/src \
	--exclude=/tests \
	--exclude=/translationfiles \
	--exclude=/tsconfig.json \
	--exclude=/vendor \
	--exclude=/webpack.* \
	$(project_dir)/ $(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(version).tar.gz | openssl base64; \
	fi

