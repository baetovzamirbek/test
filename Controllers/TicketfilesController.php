<?php

class TicketfilesController extends AbstractController
{
  public function attachAction(){
    $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);

    if (!empty($_FILES)) {
      $tempFile = $_FILES['files']['tmp_name'][0];
      $ticketId = $_POST['id'];

      $ts = uniqid();

      $tmp = explode('.', $_FILES['files']['name'][0]);
      $ext = $tmp[count($tmp)-1];

      // Validate the file type
      $fileTypes = array('php', 'exe'); // File extensions
      $fileParts = pathinfo($_FILES['files']['name'][0]);

      if (!in_array($fileParts['extension'], $fileTypes) && !empty($tempFile)) {
        $s3FilePath = "/team/ticket-{$ticketId}-post-attachment-{$ts}.{$ext}";
        // s3 upload
        $aws = \Aws\Common\Aws::factory('../app/config/amazon.php');
        $s3Client = $aws->get("s3");
        $s3Client->putObject(array(
          'Bucket' => 'crmgrowave',
          'Key' => $s3FilePath,
          'ACL' => 'private',
          'Body' => fopen($tempFile, 'r+'),
          'CacheControl' => 'max-age=604800'
        ));

        $file = new File();
        $file->parent_id = $ticketId;
        $file->parent_type = 'post-temp-' . $ts;
        $file->ext = $ext;
        $file->name = $_FILES['files']['name'][0];
        $file->size = $_FILES['files']['size'][0];
        $file->path = $s3FilePath;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file->type = finfo_file($finfo, $tempFile);

        @unlink($tempFile);

        if(!$file->save()) {
          exit( var_export($file->getMessages()));
        }

        exit(json_encode(array( 'files'=>array(
          'temp-id' => $file->file_id,
          'name' => $file->name,
          's3Path' => $file->path,
          'path' => '/ticket/getFile?f=' . urlencode(base64_encode($file->file_id))
        ))));
      } else {
        echo 'Invalid file type or empty file name';
      }
    }
  }
}