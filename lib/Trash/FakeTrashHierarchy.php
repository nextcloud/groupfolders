<?php

namespace OCA\GroupFolders\Trash;

use OC\Files\Node\Folder;
use OCP\Files\Storage\IStorage;
use OC\Files\View;

class FakeTrashHierarchy {
    protected IStorage $storage;
    protected $internalPath;
    protected $mountPoint;

    public function __construct(IStorage $storage, string $internalPath, string $mountPoint)
    {
        $this->user = \OC_User::getUser();
        $this->userRoot = \OC::$server->getUserFolder($this->user);
        $this->mountPoint = $mountPoint;
        $this->internalPath = $internalPath;
        $this->storage = $storage;
        $this->view = new View($this->userRoot->getPath() . '/');
    }

    public function rootDirExists($trashFolder, $time)
    {
            if($trashFolder->nodeExists($this->mountPoint . '.d' . $time))
                 return true;

            return false;
    }

    public function getPathAndName($foldersCreated)
    {
        $initialFilePath = pathinfo($this->internalPath, PATHINFO_DIRNAME);
        $name = explode('/', $foldersCreated)[0];

        if($initialFilePath == '.') {
                return [$foldersCreated, $foldersCreated];
        }

        return [$initialFilePath . '/' . $name, $name];
    }

    public function create($trashFolder, $neededFolders = null)
    {
            $fullPath = $this->mountPoint . '/' . $this->internalPath;
            $dir = pathinfo($fullPath, PATHINFO_DIRNAME);
            $filename = basename($fullPath);

            if(! strpos($dir, '/')) {
                        $neededFolders = $dir;
            } else {
                    if(! isset($neededFolders))
                        $neededFolders = $this->getNeededFolders($trashFolder, $dir);
            }

            $this->userRoot->newFolder($dir . '/' . $neededFolders);
            $this->moveNode($dir . '/' . $neededFolders . '/' . $filename);

            return $neededFolders;
    }

    public function moveNode($target)
    {
            $this->view->rename($this->mountPoint . '/' . $this->internalPath, $target);
    }

    public function getPathInTrash($trashFolder, $time, $internalPath)
    {
            $node = $this->mountPoint . '.d' . $time;
            if(strpos($internalPath, '/')) {
                    $node .= '/' . $internalPath;

                    return $this->createNeededFolders($trashFolder, $node);
            }

            if(! $trashFolder->nodeExists($this->mountPoint)) {
                $this->create();
                return [true, $this->moveNode($this->mountPoint . '/' . $internalPath)];
            }

        return [false, $this->mountPoint . '/'];
    }

    public function getNeededFolders($trashFolder, $fullPath, $rootDirTime = null)
    {
            $folderTree = explode('/', $fullPath);
            $recreatePath = '';

            if($rootDirTime) {
                $folderTree[0] .= '.d' . $rootDirTime;
            }

            while(! empty($folderTree)) {
                if(! $trashFolder->nodeExists(implode('/', $folderTree))) {
                        $recreatePath = end($folderTree) . '/' . $recreatePath;
                }

                unset($folderTree[array_key_last($folderTree)]);
            }

            $recreatePath = trim($recreatePath, '/');

            return $recreatePath;
    }
}
