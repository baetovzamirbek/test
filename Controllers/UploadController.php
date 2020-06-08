<?php

use Shopify\Image\Image;

/**
 * Created by PhpStorm.
 * User: ���� ���������
 * Date: 23.11.2015
 * Time: 17:20
 */
class UploadController extends AbstractController
{
  public function indexAction()
  {
    $aws = \Aws\Common\Aws::factory('../app/config/amazon.php');
    $s3Client = $aws->get("s3");

    $objects = $s3Client->getIterator('ListObjects', array(
      "Bucket" => 'sswfiles',
      "Prefix" => 'images/'
    ));

    $folders = [];
    foreach ($objects as $object) {
      if ($object['Size'] == 0 && !isset($folders[$object['Key']])) {
        $folders[$object['Key']] = [];
      } else if ($object['Size'] > 0) {
        $path_parts = pathinfo($object['Key']);
        $folders[$path_parts['dirname'] . '/'][] = $object['Key'];
      }
    }

    $this->view->folders = $folders;
  }

  public function uploadAction()
  {
    $prefix = $this->getParam('prefix', 'images/');
    $save_filename = $this->getParam('save_filename', false);
    $file_post = $_FILES['photos'];
    $file_ary = array();
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);

    if ($file_count > 0 && $file_post['name'][0] == "") {
      return $this->response->redirect($this->url->get("upload/index"), true);
    }

    for ($i=0; $i<$file_count; $i++) {
      foreach ($file_keys as $key) {
        $file_ary[$i][$key] = $file_post[$key][$i];
      }
    }

    $aws = \Aws\Common\Aws::factory('../app/config/amazon.php');
    $s3Client = $aws->get("s3");

    foreach ($file_ary as $item) {
      $token = md5(uniqid());
      $extension = pathinfo($item['name'], PATHINFO_EXTENSION);
      $filename = ($save_filename) ? basename($item['name']) : basename($token . "." . $extension);
      $s3Client->putObject(array(
        'Bucket' => 'sswfiles',
        'Key' => $prefix . $filename,
        'ACL' => 'public-read',
        'Body' => fopen($item['tmp_name'], 'r+'),
        'CacheControl' => 'max-age=604800'
      ));
      @unlink($item['tmp_name']);
    }
    return $this->response->redirect($this->url->get("upload/index"), true);
  }

  public function migrateFilesAction()
  {
    // migrate to
    $aws_prefix = 'images/apps/wishlist/';
    // migrate from
    $path = '/mnt/www/off.growave.io/public/ssw/img/apps/wishlist/';

    $aws = \Aws\Common\Aws::factory('../app/config/amazon.php');
    $s3Client = $aws->get("s3");

    $objects = $s3Client->getIterator('ListObjects', array(
      "Bucket" => 'sswfiles',
      "Prefix" => $aws_prefix
    ));

    $uploaded_files = [];
    foreach ($objects as $object) {
      if ($object['Size'] == 0) {
        continue;
      }
      $uploaded_files[] = str_replace($aws_prefix, '', $object['Key']);
    }

    $files = scandir($path);
    $uploaded_count = 0;
    foreach ($files as $file) {
      if ($file == '.' || $file == '..' || in_array($file, $uploaded_files)) {
        continue;
      }

      if ($uploaded_count >= 50) {
        break;
      }

      $s3Client->putObject(array(
        'Bucket' => 'sswfiles',
        'Key' => $aws_prefix . $file,
        'ACL' => 'public-read',
        'Body' => fopen($path . $file, 'r+'),
        'CacheControl' => 'max-age=604800'
      ));
      unlink($path . $file);
      $uploaded_count++;
    }

    print_arr($uploaded_files);
    print_arr($uploaded_count);
    print_die(111);
  }

  public function deleteAction()
  {
    $url_image = explode('https://static.socialshopwave.com/',$_REQUEST['data']);

    if (isset($url_image[1])) {
      $image_for_delete = $url_image[1];
      $aws = \Aws\Common\Aws::factory('../app/config/amazon.php');
      $s3Client = $aws->get("s3");
      $result = $s3Client->deleteObject(array(
        'Bucket' => 'sswfiles',
        'Key'    => $image_for_delete
      ));
      if ($result) {
        return $this->response->redirect($this->url->get("upload/index"), true);
      }
    }
  }

  public function heheAction()
  {
//    $this->migrateUserAvatars();
//    $this->migrateTeamAttachments();
//    $this->deleteTeamTempFiles();
//    $this->migrateClientAttachments();
//    $this->fixTeamFilesAcl();

    print_die(5555555);
  }

  private function migrateUserAvatars()
  {
    $files = File::find("parent_type='user' AND type='avatar'");
    foreach ($files as $file) {
      if (strstr($file->path, 'avatars/') !== false) {
        continue;
      }
      preg_match('/\/img\/avatar\-(\d+)-(?<ts>\d+)\.(\w+)/', $file->path, $fileParts);
      $ts = isset($fileParts['ts']) ? $fileParts['ts'] : time();

      $fileFullPath = realpath('.') . $file->path;
      $image = Image::factory();
      $image->open($fileFullPath);
      $size = min($image->height, $image->width);
      $x = ($image->width - $size) / 2;
      $y = ($image->height - $size) / 2;

      $image->resample($x, $y, $size, $size, 100, 100)
        ->write($fileFullPath)
        ->destroy();

      $s3FilePath = "/avatars/{$file->parent_id}-{$ts}.{$file->ext}";
      // s3 upload
      $aws = \Aws\Common\Aws::factory('../app/config/amazon.php');
      $s3Client = $aws->get("s3");
      $s3Client->putObject(array(
        'Bucket' => 'crmgrowave',
        'Key' => $s3FilePath,
        'ACL' => 'public-read',
        'Body' => fopen($fileFullPath, 'r+'),
        'CacheControl' => 'max-age=604800'
      ));

      $file->path = $s3FilePath;
      $file->save();

      @unlink($fileFullPath);
    }

    print_die(6666);
  }

  private function migrateTeamAttachments()
  {
    $aws = \Aws\Common\Aws::factory('../app/config/amazon.php');
    $s3Client = $aws->get("s3");

    $files = File::find(["type <> 'attachment' AND path LIKE '%/img/ticket/%'", 'limit' => 100]);
    foreach ($files as $file) {
      $fileFullPath = realpath('.') . $file->path;
      if (!file_exists($fileFullPath)) {
        $file->delete(false);
        continue;
      }
      preg_match('/\/img\/ticket\/ticket-(?<ticketId>\d+)-post-attachment/', $file->path, $fileParts);
      $ts = uniqid();
      $ticketId = isset($fileParts['ticketId']) ? $fileParts['ticketId'] : 0;
      $s3FilePath = "/team/ticket-{$ticketId}-post-attachment-{$ts}.{$file->ext}";
      // s3 upload
      $s3Client->putObject(array(
        'Bucket' => 'crmgrowave',
        'Key' => $s3FilePath,
        'ACL' => 'private',
        'Body' => fopen($fileFullPath, 'r+'),
        'CacheControl' => 'max-age=604800'
      ));

      $file->path = $s3FilePath;
      $file->save();

      @unlink($fileFullPath);
    }

    echo "<script type='application/javascript'>setTimeout(function() {
      window.location.reload();
}, 3000)</script>";
    print_die(File::count("type <> 'attachment' AND path LIKE '%/img/ticket/%'"));
  }

  private function deleteTeamTempFiles()
  {
    $files = File::find(["type = 'attachment' AND path LIKE '%/img/ticket/%'", 'limit' => 100]);
    foreach ($files as $file) {
      $file->delete();
    }

    $count = File::count("type = 'attachment' AND path LIKE '%/img/ticket/%'");

    if ($count) {
      echo "<script type='application/javascript'>setTimeout(function() {
      window.location.reload();
}, 3000)</script>";
    }

    print_die($count);
  }

  private function migrateClientAttachments()
  {
    $aws = \Aws\Common\Aws::factory('../app/config/amazon.php');
    $s3Client = $aws->get("s3");

    $files = File::find(["path LIKE '%/ticket_files/%'", 'limit' => 100]);
    $postTickets = [];
    foreach ($files as $file) {
      $fileFullPath = realpath('.') . $file->path;
      if (!file_exists($fileFullPath)) {
        $file->delete(false);
        continue;
      }

      // get ticket by post_id
      if (!isset($postTickets[$file->parent_id])) {
        $post = Post::findFirst($file->parent_id);
        $postTickets[$file->parent_id] = ($post) ? $post->ticket_id : 0;
      }
      $ticketId = $postTickets[$file->parent_id];
      $ts = uniqid();

      $s3FilePath = "/client/ticket-{$ticketId}-{$ts}.{$file->ext}";
      $s3Client->putObject(array(
        'Bucket' => 'crmgrowave',
        'Key' => $s3FilePath,
        'ACL' => 'private',
        'Body' => fopen($fileFullPath, 'r+'),
        'CacheControl' => 'max-age=604800'
      ));

      $file->path = $s3FilePath;
      $file->save();

      @unlink($fileFullPath);
    }

    $count = File::count("path LIKE '%/ticket_files/%'");
    if ($count) {
      echo "<script type='application/javascript'>setTimeout(function() {
      window.location.reload();
}, 3000)</script>";
    }
    print_die($count);
  }

  private function fixTeamFilesAcl()
  {
    $fileId = $this->getParam('fileId', 0);
    $aws = \Aws\Common\Aws::factory('../app/config/amazon.php');
    $s3Client = $aws->get("s3");

    $files = File::find(["path LIKE '%/team/%' AND file_id > {$fileId}", 'limit' => 100, 'order' => 'file_id']);
    $lastFileId = $fileId;
    foreach ($files as $file) {
      try {
        $object = $s3Client->getObjectAcl([
          'Bucket' => 'crmgrowave',
          'Key' => $file->path,
        ]);

        $lastFileId = $file->file_id;

        if (count($object['Grants']) < 2) {
          continue;
        }

        $s3Client->putObjectAcl([
          'Bucket' => 'crmgrowave',
          'ACL' => 'private',
          'Key' => $file->path,
        ]);
      } catch (\Exception $e) {
        print_arr($e->getMessage());
      }
    }

    $count = File::count("path LIKE '%/team/%' AND file_id > {$lastFileId}");
    if ($count) {
      echo "<script type='application/javascript'>setTimeout(function() {
      window.location.href = 'https://crm.growave.io/upload/hehe?fileId={$lastFileId}';
}, 1000)</script>";
    }
    print_die($count);
  }
}