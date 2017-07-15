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

## Notes

* Currently using encryption on group folders is not supported. 
* A new Group folder currently overwrites user folders with the same name. While this does not cause data loss, the users will see the new (empty!) Group folder and won’t be able to access their old folder. When the Group folder gets removed, the ‘old’ folder reappears. While we look into forcing group folders to be unique in an upcoming update, we recommend administrators to make sure the names are unique, for example by prefixing them in a certain way like `GS_` and instructing users not to name their own top-level folders in a similar way.
