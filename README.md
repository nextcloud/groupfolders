# Group folders

Admin configured folders accessible by everyone in a group.

## Notes

See [this pinned issue](https://github.com/nextcloud/groupfolders/issues/1414) to know the status of this app.

## Configure folders

Folders can be configured from *Group folders* in the admin settings.

After a folder is created, the admin can give access to the folder to one or more groups, a quota can be assigned for the folder and advanced permissions can be activated and configured.


![edit](screenshots/edit.png)

Permissions to the content of a group folder can be configured on a per-group basis.

![permissions](screenshots/permissions.png)

The configuration options include the _Write_, _Share_ and _Delete_ permissions for each group.

## Folders

Once configured, the folders will show up in the home folder for each user in the configured groups.

![folders](screenshots/folders.png)

## Advanced Permissions

_Advanced Permissions_ allows entitled users to configure permissions inside groupfolders on a per file and folder basis.

Permissions are configured by setting one or more of "Read", "Write", "Create", "Delete" or "Share" permissions to "allow" or "deny". Any permission not explicitly set will inherit the permissions from the parent folder. If multiple configured advanced permissions for a single file or folder apply for a single user (such as when a user belongs to multiple groups), the "allow" permission will overwrite any "deny" permission. Denied permissions configured for the group folder itself cannot be overwritten to "allow" permissions by the advanced permission rules.

![advanced permissions](screenshots/acl.png)

Users or whole groups can be entitled to set advanced permissions for each group folder separately on the group folders admin page.
For entitlements, only users from those groups are selectable which have to be configured selected in the Groups column.

![advanced permission entitlement](screenshots/aclAdmin.png)

## Command line configuration via occ

Group folders can be configured on the command line (cli) using the `occ` command:

- `occ groupfolders:create <name>` &rarr; create a group folder
- `occ groupfolders:delete <folder_id> [-f|--force]` &rarr; delete a group folder and all its contents
- `occ groupfolders:expire` &rarr; trigger file version and trashbin expiration (see [Nextcloud docs for versionning](https://docs.nextcloud.com/server/latest/admin_manual/configuration_files/file_versioning.html) and [Nextcloud docs for the trash bin](https://docs.nextcloud.com/server/latest/admin_manual/configuration_files/trashbin_configuration.html) for details)
- `occ groupfolders:group <folder_id> <group_id> [-d|--delete] [write|share|delete]` &rarr; assign groups and their rights to a group folder
- `occ groupfolders:list` &rarr; list configured group folders
- `occ groupfolders:permissions` &rarr; configure advanced permissions (see below for details)
- `occ groupfolders:quota <folder_id> [<quota>|unlimited]` &rarr; set a quota for a group folder
- `occ groupfolders:rename <folder_id> <name>` &rarr; rename a group folder
- `occ groupfolders:scan <folder_id>` &rarr; trigger a filescan for a group folder
- `occ groupfolders:trashbin:cleanup ` &rarr; empty the trashbin of all group folders

### Configuring advanced permissions trough occ

Advanced permissions can also be configured trough the `occ groupfolders:permissions` command.

To use the occ command you'll first need to find the id of the groupfolder you're trying to configure trough `occ groupfolders:list`.

Before configuring any advanced permissions you'll first have to enable advanced permissions for the folder using `occ groupfolders:permissions <folder_id> --enable`.
Then you can list all configured permissions trough `occ groupfolders:permissions <folder_id>`.
To disable the advanced permissions feature for a group folder, use `occ groupfolders:permissions <folder_id> --disable`.

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

## API

Group folders can be configured externally trough REST Api's.

The following REST API's are supported:

- `GET apps/groupfolders/folders`: Returns a list of all configured folders and their settings
- `POST apps/groupfolders/folders`: Create a new group folder
    - `mountpoint`: The name for the new folder
- `GET apps/groupfolders/folders/$folderId`: Return a specific configured folder and its settings
- `DELETE apps/groupfolders/folders/$folderId`: Delete a group folder
- `POST apps/groupfolders/folders/$folderId/groups`: Give a group access to a folder
    - `group`: The id of the group to be given access to the folder
- `DELETE apps/groupfolders/folders/$folderId/groups/$groupId`: Remove access from a group to a folder
- `POST apps/groupfolders/folders/$folderId/acl`: Enable/Disable folder advanced permissions
    - `acl` 1 for enable, 0 for disable.
- `POST apps/groupfolders/folders/$folderId/manageACL`: Grants/Removes a group or user the ability to manage a groupfolders' advanced permissions
    - `$mappingId`: the id of the group/user to be granted/removed access to/from the folder
    - `$mappingType`: 'group' or 'user'
    - `$manageAcl`: true to grants ability to manage a groupfolders' advanced permissions, false to remove
- `POST apps/groupfolders/folders/$folderId/groups/$groupId`: Set the permissions a group has in a folder
    - `permissions` The new permissions for the group as bitmask of [permissions constants](https://github.com/nextcloud/server/blob/b4f36d44c43aac0efdc6c70ff8e46473341a9bfe/lib/public/Constants.php#L65)
- `POST apps/groupfolders/folders/$folderId/quota`: Set the quota for a folder
    - `quota`: The new quota for the folder in bytes, user `-3` for unlimited
- `POST apps/groupfolders/folders/$folderId/mountpoint`: Change the name of a folder
    - `mountpoint`: The new name for the folder

For all `POST` calls the required parameters are listed.
