## 10.0.1

### Added

- #1706 Wrap group names
- #1710 Use already cached parents when fetching ACL
- #1718 Fix tooltips not shown
- #1795 Cast argument from string to int
- #1799 Fix OCP\Constant not found @acsfer
- #1844 Fix non user avatars in sharing sidebar
- #1713 Fix build @CarlSchwan
- #1672 Fix rollback of file version @CarlSchwan
- #1740 Don't allow to permanantly delete files if the user can't delete files
- #1726 Fix wrong object passed to the mount provider in VersionsBackend::getAllVersionedFiles
- #1775 Test the groupfolder app with multiples PHP versions @acsfer
- #1780 Switch to setup-php@v2 (v1 is deprecated) @acsfer
- #1782 Enfore the usage of int for quota
- #1779 Update node ci checks (copy workflow from master) @CarlSchwan
- #1800 Fix "Class not found" error @acsfer
- #1826 Unify folder retrival in commands @CarlSchwan
- #1836 $folder['permissions'] is always null
- #1831 fix Oracle query limit compliance


## 10.0.0

### Fixed

- #1331 Add tooltip for user/group name in sidebar ACL list @danxuliu
- #1335 Fix "contenthash" not included in chunk filename @danxuliu
- #1318 Fix #801 Folder icon in shared group folder @juliushaertl
- #1340 Cast groupfolder id to string when trying to create a new folder @juliushaertl
- #1239 Check for naming conflicts before returning the user mounts @icewind1991
- #1334 Fix deletion failing even if there's an entry in the folder listing @noiob
- #1360 Make clear arguments are ids and not names @nickvergessen
- #1346 Obtain cacheEntry for created folders and handle errors more gracefully @juliushaertl
- #1484 Only return user result once @juliushaertl
- #1534 Cancel ACL user/group search requests @juliushaertl
- #1472 Enforce string for folder id when obtaining the trash folder @juliushaertl
- #1566 Fixes build system after changes introduced by 704edc3fab11077fae13b5214271d07862c0f823 @StCyr
- #1263 check folder permissions when restoring a trashbin item @icewind1991
- #1244 Add advanced permission toggle to OCS call examples. @ATran31
- #1364 fix ACL error: user can not manage acl even assigned in setting @Nienzu
- #1374 preventDefault on folder create submit event @icewind1991
- #1388 Fixes some typo in README.md @StCyr
- #1375 fix wrong method call to check restore permissions @icewind1991
- #1403 Revert "fix ACL error: user can not manage acl even assigned in setting" @juliushaertl
- #1380 add hint for subfolder groupfolders to readme @icewind1991
- #1410 Switch to use base branch for github actions server setup @juliushaertl
- #1366 load all acl rules for a folder/search result in one go @icewind1991
- #1389 Update README.md for REST API's @StCyr
- #1415 Moved Note to the pinned issue. @pierreozoux
- #1406 Sidebar view: refresh ACL entries when fileInfo prop changes #1378 @jngrb
- #1395 Fixed searching for groups in the sharing sideview @jngrb
- #1443 l10n: Unify spelling @Valdnet
- #1444 l10n: Unify spelling @Valdnet
- #1485 Enhancement/doc update api @StCyr
- #1513 l10n: Change to a capital letter @Valdnet
- #1535 Bump node and npm version in package.json @nickvergessen
- #1543 Mock database dependency as it is being pulled in by the CacheWrapper @juliushaertl
- #1524 Fixes parameters name of manageACL REST endpoint @StCyr
- #1560 Move to shared js build configs @juliushaertl
- #1631 Correctly calculate directory sizes when using an object store primary storage @CarlSchwan
- #1602 Avoid double encoding the group name in the ACL options
- #1635 Fix fetching groups @CarlSchwan
- #1647 Load groupfolders-files.js instead of files.json  @juliushaertl
- #1648 Display unsupported messages when trying to scan object store based group folder

### Other

- Dependency updates



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
