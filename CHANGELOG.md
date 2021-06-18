## 8.2.2

* [#1487](https://github.com/nextcloud/groupfolders/pull/1487) Only return user result once
* [#1540](https://github.com/nextcloud/groupfolders/pull/1540) Cancel ACL user/group search requests
* [#1545](https://github.com/nextcloud/groupfolders/pull/1545) Enforce string for folder id when obtaining the trash folder

## 8.2.1

* [#1164](https://github.com/nextcloud/groupfolders/pull/1164) Add missing exit codes
* [#1267](https://github.com/nextcloud/groupfolders/pull/1267) Add missing return code. This relates to issue #1154
* [#1306](https://github.com/nextcloud/groupfolders/pull/1306) Fix file drop shared folders
* [#1316](https://github.com/nextcloud/groupfolders/pull/1316) check folder permissions when restoring a trashbin item
* [#1333](https://github.com/nextcloud/groupfolders/pull/1333) Add tooltip for user/group name in sidebar ACL list
* [#1337](https://github.com/nextcloud/groupfolders/pull/1337) Fix "contenthash" not included in chunk filename
* [#1342](https://github.com/nextcloud/groupfolders/pull/1342) Cast groupfolder id to string when trying to create a new folder
* [#1350](https://github.com/nextcloud/groupfolders/pull/1350) Check for naming conflicts before returning the user mounts
* [#1398](https://github.com/nextcloud/groupfolders/pull/1398) preventDefault on folder create submit event
* [#1401](https://github.com/nextcloud/groupfolders/pull/1401) fix wrong method call to check restore permissions
* [#1431](https://github.com/nextcloud/groupfolders/pull/1431) Sidebar view: refresh ACL entries when fileInfo prop changes #1378
* [#1436](https://github.com/nextcloud/groupfolders/pull/1436) Fixed searching for groups in the sharing sideview
* [#1466](https://github.com/nextcloud/groupfolders/pull/1466) Obtain cacheEntry for created folders and handle errors more gracefully


## 8.2.0

* [#1161](https://github.com/nextcloud/groupfolders/pull/1161) Make database schema compatible with Oracle

## 8.1.1

* [#1086](https://github.com/nextcloud/groupfolders/pull/1086) Load files client extension through file list plugin
* [#1108](https://github.com/nextcloud/groupfolders/pull/1108) Run frontend build in github actions
* [#1113](https://github.com/nextcloud/groupfolders/pull/1113) Fix occ when files_trashbin is disabled
* [#1116](https://github.com/nextcloud/groupfolders/pull/1116) Make sure to only move in cache if it was not already done by the storage
* [#1129](https://github.com/nextcloud/groupfolders/pull/1129) 1 query to obtain group folders
* [#1131](https://github.com/nextcloud/groupfolders/pull/1131) Use the proper paremter type for the IN query

## 8.1.0

- [#1067](https://github.com/nextcloud/groupfolders/pull/1067) OCC command to empty the trashbin

## 8.0.0

- Show inherited ACLs in the files sidebar
- Improve performance when querying managing users/groups
- Fix issue causing "$path is an integer" logging in versions backend
- Nextcloud 20 compatiblity

## 6.0.6

- ACL: Increase performance by selecting on indexed column @Deltachaos
- Use a lazy folder @rullzer

## 6.0.5

- Nextcloud 19 compatibility

## 6.0.4

- Check ACL before restoring files from the trashbin
- Do not allow restoring files at an existing target
- Return the mountpoint owner as a fallback
- Bump dependencies

## 6.0.3

- Do not ship unneeded files with the release

## 6.0.2

- Allow to detect the file path for shares inside of groupfolders, e.g. when they are matched in workflow rules
- Bump dependencies

## 6.0.1

- Fix sharing files from groupfolders trough ocs api
- Show the full path including groupfolder in trashbin

## 6.0.0

- Nextcloud 18 compatibility
- Search for users by display name
- Only check for admin permissions if needed
- Check for ACL list in trash backend
- Bump dependencies

## 5.0.4
- Fix etag propagation which caused the desktop client not syncing changes
- Check if the parent folder is updatable when moving

## 5.0.3
- Handle advanced permission rules for users/groups that no longer exist

## 5.0.2
- Allow longer path as groupfolder mount points

## 5.0.1
- Improved error handling when removing items from trash
- Fix groupfolders breaking updating calendar details    

## 5.0.0
- 17 compatiblity
- Use groupfolder storage for versioning

## 4.1.2
- Allow longer path as groupfolder mount points

## 4.1.1
- Improved error handling when removing items from trash
- Fix groupfolders breaking updating calendar details

## 4.1.0
- Allow groups to manage ACL permissions
- Bump dependencies
- Fix IE11 compatibility
- Check for naming conflicts before returning the user mouns

## 4.0.5
- Bump dependencies
- Update translations
- Proper values returned from Storage Wrapper fixing some etag bugs

## 4.0.4
- Fix issue with ACL cache returning empty result sets
- Bump dependencies

## 4.0.3
- Fix Collabora documents opening in read only in some cases
- Improve dark mode support

## 4.0.2
- Fix handling of public pages
- Fix advanced permissions not applying on the root of a groupfolder

## 4.0.1
- Fix not being able to delete advanced permission rules from the web interface
- Use display names in advanced permissions interface
- Fix not being able to open files in Collabora Online that don't have share permissions

## 4.0.0
- Access control list support for advanced permission management
- Improve performance of listing group folders with large filecache tables
- Block deleting of folders that have non-deletable items in them
- Improved admin page layout
- Fix groupfolder icons sometimes not being themed correctly.
- Fix moving shared groupfolder items to trashbin.

## 3.0.1

This release is aimed for Nextcloud 14/15 users who upgraded to 3.0.0 which was
falsely marked as compatible for those Nextcloud releases.

Additionally the following fixes are included

- Fix groupfolder icons sometimes not being themed correctly.
- Fix moving shared groupfolder items to trashbin. 

## 1.2.0

 - Allow changing the mount point of existing group folders
 - Add OCS api for managing folders
 - Fix folder icons in public shares
 - Merge permissions if a user has access to a folder trough multiple groups
