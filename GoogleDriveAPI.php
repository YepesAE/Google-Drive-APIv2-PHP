<?php 
// Importable class with various custom functions to manage google Drive documents
// folder, and permissions from php. 

include 'path/to/autoload.php'

class googleManager(){

	  // Function that connects to the Google Drive account created from the Google Cloud Console
	  function connectToDrive(){
      putenv('GOOGLE_APPLICATION_CREDENTIALS='.'path/to/credentials.json');

      $client = new Google_client();
      $client->useApplicationDefaultCredentials();
      $client-> SetScopes(['https://www.googleapis.com/auth/drive',
                           'https://www.googleapis.com/auth/drive.file',
                           'https://www.googleapis.com/auth/drive.readonly',
                           'https://www.googleapis.com/auth/drive.metadata.readonly',
                           'https://www.googleapis.com/auth/drive.appdata',
                           'https://www.googleapis.com/auth/drive.apps.readonly',
                           'https://www.googleapis.com/auth/drive.metadata',
                           'https://www.googleapis.com/auth/drive.photos.readonly']);
      $service = new Google_Service_Drive($client);
      return ["client" => $client, "service" => $service];
    }


    // Returns a file given a parent and a name. 
    function getFileIdInParentFolder($parentId, $filename){
      $files = self::getFilesFromFolderId($parentId);
      foreach ($files as $file) {
          if($file['name'] == $filename){
            return $file['id'];
          }
      }
    }


    // Returns all files in a folder
    function getFilesFromFolderId($parentId){
      $connection = self::connectToDrive();
      $optParams = array(
        'pageSize' => 1000,
        'orderBy' => "name",
        'q' => "'".$parentId."' in parents"
        );
      $list = $connection["service"]->files->listFiles($optParams);
      return $list;
    }


    // Checks for file with specific name in your Drive and deletes it
    function clearFileByName($filename){
      $connection = self::connectToDrive();
      $list = $connection["service"]->files->listFiles();
      foreach ($list as $file) {
        if($file["name"] == $filename){
          $connection["service"]->files->delete($file["id"]);
        }
      }
    }


    // Function that checks if folder with specific name already exist in another folder.
    // Notice that this is a linear function, the more folders you have, the more it will take to 
    // check.
    function checkFolderInParent($filename, $parentId){
      $connection = self::connectToDrive();
      $optParams = array(
        'pageSize' => 1000,
        'orderBy' => "folder",
        'q' => "'".$parentId."' in parents"
        );
      $list = $connection["service"]->files->listFiles($optParams);
      foreach ($list as $file) {
        if($file["name"] == $filename){
          return true;
        }
      }
      return false;
    }


    // Creates a folder with a given name into a given parent folder.
    function createFolderWithParent($parentId, $folder_name, $description){
      if(!self::checkFolderInParent($folder_name, $parentId)){
        $connection = self::connectToDrive();
        $file_path = "/";

        $file = new Google_Service_Drive_DriveFile();

        $file->setParents(array($parentId));
        $file->setName($folder_name);
        $file->setMimeType("application/vnd.google-apps.folder");
        $file->setDescription($description);

        $res = $connection["service"]->files->create(
            $file,
            array(
                'data' => file_get_contents($file_path),
                'mimeType' => "application/vnd.google-apps.folder",
                'uploadType' => 'media'
            )
        );
      } 
    }


    // Given a file path this function inserts a file into a folder.
    // If mimeType is '' google will add it automatically.
    function insertFile($title, $description, $parentId, $mimeType, $filename) {
      $connection = self::connectToDrive();

      $file = new Google_Service_Drive_DriveFile();
      $file->setName($title);
      $file->setDescription($description);
      $file->setMimeType($mimeType);
    
      if ($parentId != null) {
        $file->setParents(array($parentId));
      }
    
      try {
        $data = file_get_contents($filename);
    
        $createdFile = $connection["service"]->files->create($file, array(
          'data' => $data,
          'mimeType' => $mimeType,
        ));
    
        return $createdFile;
      } catch (Exception $e) {
        echo $e;
      }
    }


    // Gives an specific role to a user for a specific file.
    // fileId can also be a folderId.
    // Roles can be found here https://developers.google.com/drive/api/guides/ref-roles
    function addRole($fileId, $userMail, $role){
      $optParams = array(
        'sendNotificationEmail' => false
      );
      try{
        $connection = self::connectToDrive();
        $permission = new Google_Service_Drive_Permission();
        $permission->setRole($role);
        $permission->setType('user');
        $permission->setEmailAddress($userMail);

        $connection["service"]->permissions->create($fileId, $permission, $optParams);
      }catch (Exception $e){
        echo $e;
      }
    }


    // Function that returns all the permissions for a given fileId.
    function retrievePermissions($fileId) {
      $connection = self::connectToDrive();
      $optParams = array(
        'fields' => '*'
      );
      $results = $connection["service"]->files->get($fileId, $optParams);
      $permissions = $results->getPermissions();

      return $permissions;
    }


    // Function that deletes a permission from a file.
    function removePermission($fileId, $permissionId) {
      $connection = self::connectToDrive();
      try {
        $connection["service"]->permissions->delete($fileId, $permissionId);
      } catch (Exception $e) {
        echo $e;
    }


    // Function that finds the permission given an email and removes it if found.
    function removePermissionByMail($fileId, $mail){
      $permissions = self::retrievePermissions($fileId);
      foreach ($permissions as $p) {
          if($p["emailAddress"] == $mail){
              self::removePermission($fileId, $p["id"]);
          }
      }
    }


    // Function  that changes the permission of a file given a role.
    function editPermission($service, $fileId, $permissionId, $role) {
      try {
          $updatedPerm = new Google_Service_Drive_Permission();
          $updatedPerm->setRole($role);
          $result = $service->permissions->update($fileId,$permissionId,$updatedPerm);
      } catch (Exception $e) {
        echo $e;
      }
    }


    // Edit the permission of specific user in specific file
    function editPermissionByMail($service, $fileId, $mail, $role){
      $permissions = googleManager :: retrievePermissions($service, $fileId);
      foreach ($permissions as $p) {
          if($p["emailAddress"] == $mail){
              googleManager :: editPermission($service, $fileId, $p["id"], $role);
          }
      }
    }


    // Given a File ID makes it visible for everyone
    function giveAnyonePermission($fileId){
      $optParams = array(
        'sendNotificationEmail' => false
      );
      try{
        $connection = self::connectToDrive();
        $permission = new Google_Service_Drive_Permission();
        $permission->setRole("reader");
        $permission->setType('anyone');

        $connection["service"]->permissions->create($fileId, $permission, $optParams);
      }catch (Exception $e){
        echo $e;
      }
    }



    // Function that creates a shortcut of a folder into another folder
    function copyFolderInParent($destinationFolderId, $sourceFolderId){
      $connection = self::connectToDrive();
      $file_path = "/";

      $file = new Google_Service_Drive_DriveFile(array(
        'title' => 'Shortcut to Target Folder',
        'mimeType' => 'application/vnd.google-apps.shortcut',
        'shortcutDetails' => array('targetId' => $sourceFolderId)
      ));

      $file->setParents(array($destinationFolderId));

      $resultado = $connection["service"]->files->create(
        $file,
        array(
            'data' => file_get_contents($file_path),
            'mimeType' => "application/vnd.google-apps.shortcut",
            'uploadType' => 'media'
        )
      );
    }

}
?>
