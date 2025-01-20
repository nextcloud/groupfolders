<!--
  - SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Team folders

[![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/groupfolders)](https://api.reuse.software/info/github.com/nextcloud/groupfolders)

📁👩‍👩‍👧‍👦 Admin configured folders accessible by everyone in a group in Nextcloud.

## References

* **[Known issues and FAQs](https://github.com/nextcloud/groupfolders/issues/1414)**
* **[Release Notes](https://github.com/nextcloud-releases/groupfolders/releases)[^1]**
* **[App Store](https://apps.nextcloud.com/apps/groupfolders)**

[^1]: The releases are now managed in a [dedicated release repository](https://github.com/nextcloud-releases/groupfolders/releases). The releases in this repository may be outdated.

## Configuring Team folder

Team folders can be configured through *Team folders* under *Administration settings*.

After a folder is created, the admin can give access to the folder to one or more groups, a quota can be assigned for the folder and advanced permissions can be activated and configured.


![edit](screenshots/edit.png)

Permissions to the content of a Team folder can be configured on a per-group basis.

![permissions](screenshots/permissions.png)

The configuration options include the _Write_, _Share_ and _Delete_ permissions for each group.

## Using Team folders

Once configured, the folders will show up in the home folder for each user in the configured groups.

![folders](screenshots/folders.png)

## Setting Advanced Permissions

_Advanced Permissions_ allows entitled users to configure permissions inside Team folders on a per file and folder basis.

Permissions are configured by setting one or more of "Read", "Write", "Create", "Delete" or "Share" permissions to "allow" or "deny". Any permission not explicitly set will inherit the permissions from the parent folder. If multiple configured advanced permissions for a single file or folder apply for a single user (such as when a user belongs to multiple groups), the "allow" permission will overwrite any "deny" permission. Denied permissions configured for the Team folder itself cannot be overwritten to "allow" permissions by the advanced permission rules.

Take a look at this example to understand how to properly configure Advanced Permissions for more complex scenarios with multiple groups or Teams:
1. You have the groups "Management", "Employees" with some users only belonging to "Management", but everyone being part of "Employees".
2. You configure a Team folder and add the groups "Management", "Employees" with "Read" and "Write" permissions.
3. For a sub folder in the Team folder you explicitly configure the group "Employees" to have NO "Write" permission, but still "Read" permission.
4. For the same sub folder you configure "Management" to have "Read" and "Write" permissions.

Now only users in the "Management" group can write in the sub folder, while all users in the "Employees" group can not.

All ACL rules always need to have the least permissions for any user/group/Team with access to the sub folder.
For those users/groups/Teams that need more permissions you need to add a second rule that grants them the desired permissions.

![advanced permissions](screenshots/acl.png)

Users or whole groups can be entitled to set advanced permissions for each Team folder separately on the Team folders admin page.
For entitlements, only users from those groups are selectable which have to be configured selected in the Groups column.

![advanced permission entitlement](screenshots/aclAdmin.png)

## Configuration parameters

Some settings are currently only exposed via `config/config.php`:

### Default quota for new Team folders

```injectablephp
'groupfolders.quota.default' => -3,
```

The special value `-3` means unlimited and any other value is the quota limit in bytes.

## Command-line interface management and configuration (via `occ`)

Team folders can be configured and managed from the command-line interface (CLI). This is accomplished by using the `occ` command. 

The `occ` command is utilized throughout Nextcloud for many operations and is not specific to Team folders. When the Team folders app is enabled, the `occ` command gains additional functionality specific to Team folders.

If you're unfamiliar with `occ` see [Using the occ command](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/occ_command.html) in the Nextcloud Server Administration Guide for general guidance.

### Commands Available

- `occ groupfolders:create <name>` &rarr; create a Team folder
- `occ groupfolders:delete <folder_id> [-f|--force]` &rarr; delete a Team folder and all its contents
- `occ groupfolders:expire` &rarr; trigger file version and trashbin expiration (see [Nextcloud docs for versioning](https://docs.nextcloud.com/server/latest/admin_manual/configuration_files/file_versioning.html) and [Nextcloud docs for the trash bin](https://docs.nextcloud.com/server/latest/admin_manual/configuration_files/trashbin_configuration.html) for details)
- `occ groupfolders:group <folder_id> <group_id> [-d|--delete] [write|share|delete]` &rarr; assign groups and their rights to a Team folder
- `occ groupfolders:list` &rarr; list configured Team folders
- `occ groupfolders:permissions` &rarr; configure advanced permissions (see below for details)
- `occ groupfolders:quota <folder_id> [<quota>|unlimited]` &rarr; set a quota for a Team folder
- `occ groupfolders:rename <folder_id> <name>` &rarr; rename a Team folder
- `occ groupfolders:scan <folder_id>` &rarr; trigger a filescan for a Team folder
- `occ groupfolders:trashbin:cleanup` &rarr; empty the trashbin of all Team folders
- `occ config:app:set groupfolders enable_encryption --value="true"` &rarr; activate encryption (server-side) support

### Configuring Advanced Permissions via `occ`

Advanced permissions can also be configured through the `occ groupfolders:permissions` command, but must be enabled first.

#### Enabling

Before configuring any advanced permissions you'll first have to enable advanced permissions for the folder using `occ groupfolders:permissions <folder_id> --enable`. To do this you'll first need to find the `folder_id` of the Team folder you're trying to configure. You can use `occ groupfolders:list` to find the `folder_id` of the target folder.

#### Using

Then you can list all configured permissions trough `occ groupfolders:permissions <folder_id>`.

```
occ groupfolders:permissions 1
+------------+--------------+-------------+
| Path       | User/Group   | Permissions |
+------------+--------------+-------------+
| folder     | group: admin | +write      |
| folder/sub | user: admin  | +share      |
|            | user: test   | -share      |
+------------+--------------+-------------+
```

Permissions for files and folders can be set trough `occ groupfolders:permissions <folder_id> --group <group_id> <path> -- <permissions>` to set permissions for a group or `occ groupfolders:permissions <folder_id> --user <user_id> <path> -- <permissions>` to set permissions for a single user.

`<permissions>` can be one or more of the following options: `-read`, `+read`, `-write`, `+write`, `-create`, `+create`, `-delete`, `+delete`, `-share` or `+share` to set the set the respective permission to "deny" or "allow".
You can delete a rule by passing `clear` as the `<permissions>` field.
Note: An advanced permission settings set always needs to be complete (for example `+read -create +delete`) and not just incremental (for example `-create`).
Not mentioned options (in the above example that's _write_ and _share_) are interpreted as _inherited_.

To help with configuring nested permission rules, you can check the effective permissions a user has for a path using `occ groupfolders:permissions <folder_id> --user <user_id> <path> --test`.

To manage the users or groups entitled to set advanced permissions, use `occ groupfolders:permissions <folder_id> [[-m|--manage-add] | [-r|--manage-remove]] [[-u|--user <user_id>] | [-g|--group <group_id>]]`.

#### Disabling

To disable the advanced permissions feature for a Team folder, use `occ groupfolders:permissions <folder_id> --disable`.

## APIs

### REST API

See the [OpenAPI specification](openapi.json) to learn about all available API endpoints: https://petstore.swagger.io/?url=https://raw.githubusercontent.com/nextcloud/groupfolders/master/openapi.json

### WebDAV API

Team folders are also exposed through a separate [WebDAV API](https://docs.nextcloud.com/server/latest/user_manual/en/files/access_webdav.html) at `/remote.php/dav/groupfolders/<user id>`.

In addition to browsing the contents of the Team folders, you can also request the mount point for the Team folder by requesting the `{http://nextcloud.org/ns}mount-point` property.

