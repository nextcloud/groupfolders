/**
 * This file was auto-generated by openapi-typescript.
 * Do not make direct changes to the file.
 */

export type paths = {
    "/index.php/apps/groupfolders/delegation/groups": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Returns the list of all groups */
        get: operations["delegation-get-all-groups"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/index.php/apps/groupfolders/delegation/circles": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Returns the list of all visible circles */
        get: operations["delegation-get-all-circles"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/index.php/apps/groupfolders/delegation/authorized-groups": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Get the list Groups related to classname. */
        get: operations["delegation-get-authorized-groups"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/index.php/apps/groupfolders/folders": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Gets all Groupfolders */
        get: operations["folder-get-folders"];
        put?: never;
        /**
         * Add a new Groupfolder
         * @description This endpoint requires password confirmation
         */
        post: operations["folder-add-folder"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/index.php/apps/groupfolders/folders/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Gets a Groupfolder by ID */
        get: operations["folder-get-folder"];
        /**
         * Set the mount point of a Groupfolder
         * @description This endpoint requires password confirmation
         */
        put: operations["folder-set-mount-point"];
        post?: never;
        /**
         * Remove a Groupfolder
         * @description This endpoint requires password confirmation
         */
        delete: operations["folder-remove-folder"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/index.php/apps/groupfolders/folders/{id}/groups": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Add access of a group for a Groupfolder
         * @description This endpoint requires password confirmation
         */
        post: operations["folder-add-group"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/index.php/apps/groupfolders/folders/{id}/groups/{group}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Set the permissions of a group for a Groupfolder
         * @description This endpoint requires password confirmation
         */
        post: operations["folder-set-permissions"];
        /**
         * Remove access of a group from a Groupfolder
         * @description This endpoint requires password confirmation
         */
        delete: operations["folder-remove-group"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/index.php/apps/groupfolders/folders/{id}/manageACL": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Updates an ACL mapping
         * @description This endpoint requires password confirmation
         */
        post: operations["folder-set-manageacl"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/index.php/apps/groupfolders/folders/{id}/quota": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Set a new quota for a Groupfolder
         * @description This endpoint requires password confirmation
         */
        post: operations["folder-set-quota"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/index.php/apps/groupfolders/folders/{id}/acl": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Toggle the ACL for a Groupfolder
         * @description This endpoint requires password confirmation
         */
        post: operations["folder-setacl"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/index.php/apps/groupfolders/folders/{id}/mountpoint": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Rename a Groupfolder
         * @description This endpoint requires password confirmation
         */
        post: operations["folder-rename-folder"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/index.php/apps/groupfolders/folders/{id}/search": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Searches for matching ACL mappings */
        get: operations["folder-acl-mapping-search"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
};
export type webhooks = Record<string, never>;
export type components = {
    schemas: {
        AclManage: {
            displayname: string;
            id: string;
            /** @enum {string} */
            type: "user" | "group" | "circle";
        };
        Applicable: {
            displayName: string;
            /** Format: int64 */
            permissions: number;
            /** @enum {string} */
            type: "group" | "circle";
        };
        Capabilities: {
            groupfolders?: {
                appVersion: string;
                hasGroupFolders: boolean;
            };
        };
        Circle: {
            sid: string;
            displayname: string;
        };
        DelegationCircle: {
            singleId: string;
            displayName: string;
        };
        DelegationGroup: {
            gid: string;
            displayName: string;
        };
        Folder: {
            /** Format: int64 */
            id: number;
            mount_point: string;
            group_details: {
                [key: string]: components["schemas"]["Applicable"];
            };
            groups: {
                [key: string]: number;
            };
            /** Format: int64 */
            quota: number;
            /** Format: int64 */
            size: number;
            acl: boolean;
            manage: components["schemas"]["AclManage"][];
        };
        Group: {
            gid: string;
            displayname: string;
        };
        OCSMeta: {
            status: string;
            statuscode: number;
            message?: string;
            totalitems?: string;
            itemsperpage?: string;
        };
        User: {
            uid: string;
            displayname: string;
        };
    };
    responses: never;
    parameters: never;
    requestBodies: never;
    headers: never;
    pathItems: never;
};
export type $defs = Record<string, never>;
export interface operations {
    "delegation-get-all-groups": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description All groups returned */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: components["schemas"]["DelegationGroup"][];
                        };
                    };
                };
            };
        };
    };
    "delegation-get-all-circles": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description All circles returned */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: components["schemas"]["DelegationCircle"][];
                        };
                    };
                };
            };
        };
    };
    "delegation-get-authorized-groups": {
        parameters: {
            query?: {
                /** @description If the classname is - OCA\GroupFolders\Settings\Admin : It's reference to fields in Admin Privileges. - OCA\GroupFolders\Controller\DelegationController : It's just to specific the subadmins. They can only manage groupfolders in which they are added in the Advanced Permissions (groups only) */
                classname?: string;
            };
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Authorized groups returned */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: components["schemas"]["DelegationGroup"][];
                        };
                    };
                };
            };
        };
    };
    "folder-get-folders": {
        parameters: {
            query?: {
                /** @description Filter by applicable groups */
                applicable?: 0 | 1;
            };
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Groupfolders returned */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                [key: string]: components["schemas"]["Folder"];
                            };
                        };
                    };
                };
            };
            /** @description Storage not found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
    "folder-add-folder": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description Mountpoint of the new Groupfolder */
                    mountpoint: string;
                };
            };
        };
        responses: {
            /** @description Groupfolder added successfully */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: components["schemas"]["Folder"];
                        };
                    };
                };
            };
            /** @description Groupfolder not found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
    "folder-get-folder": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                /** @description ID of the Groupfolder */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Groupfolder returned */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: components["schemas"]["Folder"];
                        };
                    };
                };
            };
            /** @description Groupfolder not found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
    "folder-set-mount-point": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                /** @description ID of the Groupfolder */
                id: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description New mount point path */
                    mountPoint: string;
                };
            };
        };
        responses: {
            /** @description Mount point changed successfully */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {boolean} */
                                success: true;
                            };
                        };
                    };
                };
            };
        };
    };
    "folder-remove-folder": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                /** @description ID of the Groupfolder */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Groupfolder removed successfully */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {boolean} */
                                success: true;
                            };
                        };
                    };
                };
            };
            /** @description Groupfolder not found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
    "folder-add-group": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                /** @description ID of the Groupfolder */
                id: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description Group to add access for */
                    group: string;
                };
            };
        };
        responses: {
            /** @description Group access added successfully */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {boolean} */
                                success: true;
                            };
                        };
                    };
                };
            };
            /** @description Groupfolder not found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
    "folder-set-permissions": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                /** @description ID of the Groupfolder */
                id: number;
                /** @description Group for which the permissions will be set */
                group: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /**
                     * Format: int64
                     * @description New permissions
                     */
                    permissions: number;
                };
            };
        };
        responses: {
            /** @description Permissions updated successfully */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {boolean} */
                                success: true;
                            };
                        };
                    };
                };
            };
            /** @description Groupfolder not found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
    "folder-remove-group": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                /** @description ID of the Groupfolder */
                id: number;
                /** @description Group to remove access from */
                group: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Group access removed successfully */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {boolean} */
                                success: true;
                            };
                        };
                    };
                };
            };
            /** @description Groupfolder not found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
    "folder-set-manageacl": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                /** @description ID of the Groupfolder */
                id: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description Type of the ACL mapping */
                    mappingType: string;
                    /** @description ID of the ACL mapping */
                    mappingId: string;
                    /** @description Whether to enable or disable the ACL mapping */
                    manageAcl: boolean;
                };
            };
        };
        responses: {
            /** @description ACL mapping updated successfully */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {boolean} */
                                success: true;
                            };
                        };
                    };
                };
            };
            /** @description Groupfolder not found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
    "folder-set-quota": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                /** @description ID of the Groupfolder */
                id: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /**
                     * Format: int64
                     * @description New quota in bytes
                     */
                    quota: number;
                };
            };
        };
        responses: {
            /** @description New quota set successfully */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {boolean} */
                                success: true;
                            };
                        };
                    };
                };
            };
            /** @description Groupfolder not found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
    "folder-setacl": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                /** @description ID of the Groupfolder */
                id: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description Whether ACL should be enabled or not */
                    acl: boolean;
                };
            };
        };
        responses: {
            /** @description ACL toggled successfully */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {boolean} */
                                success: true;
                            };
                        };
                    };
                };
            };
            /** @description Groupfolder not found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
    "folder-rename-folder": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                /** @description ID of the Groupfolder */
                id: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description New Mountpoint of the Groupfolder */
                    mountpoint: string;
                };
            };
        };
        responses: {
            /** @description Groupfolder renamed successfully */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {boolean} */
                                success: true;
                            };
                        };
                    };
                };
            };
            /** @description Groupfolder not found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
    "folder-acl-mapping-search": {
        parameters: {
            query?: {
                /** @description String to search by */
                search?: string;
            };
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                /** @description The ID of the Groupfolder */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description ACL Mappings returned */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                users: components["schemas"]["User"][];
                                groups: components["schemas"]["Group"][];
                                circles: components["schemas"]["Circle"][];
                            };
                        };
                    };
                };
            };
            /** @description Not allowed to search */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
}
