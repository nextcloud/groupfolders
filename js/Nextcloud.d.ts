interface EscapeOptions {
	escape?: boolean;
}

declare namespace OC {
	namespace Util {
		function humanFileSize(size: number): string;

		function computerFileSize(size: string): number;
	}

	namespace dialogs {
		function info(text: string, title: string, callback: () => void, modal?: boolean): void;

		function confirm(text: string, title: string, callback: (result: boolean) => void, modal?: boolean): void;

		function confirmHtml(text: string, title: string, callback: (result: boolean) => void, modal?: boolean): void;

		function prompt(text: string, title: string, callback: (result: string) => void, modal?: boolean, name?: string, password?: boolean): void;

		function filepicket(title: string, callback: (result: string | string[]) => void, multiselect?: boolean, mimetypeFilter?: string, modal?: boolean): void;
	}

	function generateUrl(url: string, parameters?: { [key: string]: string }, options?: EscapeOptions)

	function linkToOCS(service: string, version: number): string;

	function imagePath(app: string, file: string): string;

	const PERMISSION_CREATE = 4;
	const PERMISSION_READ = 1;
	const PERMISSION_UPDATE = 2;
	const PERMISSION_DELETE = 8;
	const PERMISSION_SHARE = 16;
	const PERMISSION_ALL = 31;
}

declare function t(app: string, string: string, vars?: { [key: string]: string }, count?: number, options?: EscapeOptions): string;

declare const oc_config: {
	blacklist_files_regex: string;
	enable_avatars: boolean;
	last_password_link: string | null;
	modRewriteWorking: boolean;
	session_keepalive: boolean;
	session_lifetime: boolean;
	"sharing.maxAutocompleteResults": number;
	"sharing.minSearchStringLength": number;
	version: string;
	versionString: string;
};

declare module 'NC' {
	export interface OCSResult<T> {
		ocs: {
			data: T;
			meta: {
				status: 'ok' | 'failure';
				message: string;
				statuscode: number;
				totalitems: number;
				itemsperpage: number;
			}
		}
	}
}
