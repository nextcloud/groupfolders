# Group folders

Admin configured folders shared by everyone in a group.

## Configure folders

Folders can be configured from *Group folders* in the admin settings.

After a folder is created, the admin can give access to the folder to one or more groups and a quota can be assigned for the folder.


![edit](screenshots/edit.png)

Permissions to the content of a group folder can be configured on a per-group basis.

![permissions](screenshots/permissions.png)

## Folders

Once configured, the folders will show up in the home folder for each user in the configured groups.

![folders](screenshots/folders.png)

## Advanced Permissions

Starting with Groupfolders 2.1.0 and Nextcloud 16 you can enable "Advanced Permissions", this allows admins to configure permissions
inside groupfolders on a per file and folder basis

![advanced permissions](screenshots/acl.png)

Advanced permissions have to be enabled for each groupfolder separably, after which an administrator can configure permissions for files and folders
trough the web interface under the share options (if the administrator has access to the groupfolder) or trough an occ command.

Permissions are configure by setting one or more of "Read", "Write", "Create", "Delete" or "Share" permissions to "allow" or "deny", any permission not set
will inherit the permissions from the parent folder. If multiple configured permissions for a single file or folder apply for a single user
(such as when a user belongs to multiple groups), the "allow" permission will overwrite any "deny" permission.

### Configuring advanced permissions trough occ

Advanced permissions can also be configured trough the `occ groupfolders:permissions` command.

To use the occ command you'll first need to find the id of the groupfolder you're trying to configure trough `occ groupfolders:list`.

Before configuring any permissions you'll first have to enable advanced permissions for the folder using `occ groupfolders:permissions <folder_id> --enable`.
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

Permissions for files and folders can be set trough `occ groupfolders:permissions <folder_id> --group <group> <path> -- <permissions>` to
set permissions for a group or `occ groupfolders:permissions <folder_id> --user <user> <path> -- <permissions>` to set permissions for a single user.

`<permissions>` can be one or more of the following options: `-read`, `+read`, `-write`, `+write`, `-create`, `+create`, `-delete`, `+delete`, `-share` or `+share`
to set the set the respective permission to "deny" or "allow".
You can delete a rule by passing `clear` as the `<permissions>` field.

To help with configuring nested permission rules, you can check the effective permissions a user has for a path using `occ groupfolders:permissions <folder_id> --user <user> <path> --test`. 

## Notes

* Currently using encryption on group folders is not supported, all files stored within a group folder will be stored unencrypted.
* A new Group folder currently overwrites user folders with the same name. While this does not cause data loss, the users will see the new (empty!) Group folder and won’t be able to access their old folder. When the Group folder gets removed, the ‘old’ folder reappears. While we look into forcing group folders to be unique in an upcoming update, we recommend administrators to make sure the names are unique, for example by prefixing them in a certain way like `GS_` and instructing users not to name their own top-level folders in a similar way.
* Currently actions will not be recorded in Activity-Stream
* Folders will appear as external storage and may need to be addressed per client-basis for download

## API

Group folders can be configured externally trough the OCS Api.

For all `POST` calls the required parameters are listed, for more information about how to use an OCS api see the [Nextcloud documentation on the topic](https://docs.nextcloud.com/server/stable/developer_manual/client_apis/OCS/index.html)

The following OCS calls are supported.

- `GET apps/groupfolders/folders`: Returns a list of call configured folders and their settings
- `POST apps/groupfolders/folders`: Create a new group folder.
    - `mountpoint`: The name for the new folder.
- `GET apps/groupfolders/folders/$folderId`: Return a specific configured folder and it's settings
- `DELETE apps/groupfolders/folders/$folderId`: Delete a group folder.
- `POST apps/groupfolders/folders/$folderId/groups`: Give a group access to a folder
    - `group`: The id of the group to be given access to the folder.
- `DELETE apps/groupfolders/folders/$folderId/groups/$groupId`: Remove access from a group to a folder.
- `POST apps/groupfolders/folders/$folderId/groups/$groupId`: Set the permissions a group has in a folder
    - `permissions` The new permissions for the group as bitmask of [permissions constants](https://github.com/nextcloud/server/blob/b4f36d44c43aac0efdc6c70ff8e46473341a9bfe/lib/public/Constants.php#L65)
- `POST apps/groupfolders/folders/$folderId/quota`: Set the quota for a folder.
    - `quota`: The new quota for the folder in bytes, user `-3` for unlimited.
- `POST apps/groupfolders/folders/$folderId/mountpoint`: Change the name of a folder.
    - `mountpoint`: The new name for the folder.
