<?php

namespace TylerKing\ThemeHandler;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Touki\FTP\FTP;
use Touki\FTP\Model\File;
use Touki\FTP\Model\Directory;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;

class UploadCommand extends BaseCommand {
  protected function configure() {
    $this
      ->setName('theme:upload')
      ->setDescription('Uploads files')
      ->addArgument(
        'files',
        InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
        'Specify files to upload'
      )
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    # Start the FTP connection
    $ftp = $this->startFTP();

    $files = $input->getArgument('files');
    if (sizeof($files) === 0) {
      # Upload all the files since none are supplied
      $this->uploadAllFiles($output, $ftp);
    } else {
      # We have a list, upload it
      $this->uploadFiles($output, $ftp, $files);
    }
    
    $this->stopFTP();
  }
  
  private function uploadAllFiles(OutputInterface $output, $ftp) {
    $i        = 1;
    $files    = new RecursiveDirectoryIterator(getcwd());
    $iterator = new RecursiveIteratorIterator($files);
    
    foreach($iterator as $file) {      
      $file_base      = $this->getFileBase($file->getPathname());
      $directory_base = pathinfo($file_base, PATHINFO_DIRNAME);
      
      # Ignore the file? (and dots)
      if ($this->isIgnoredFile($file_base) === true || substr($file_base, 0, 1) == '.' || ! is_file($file->getPathname())) {
        continue;
      }
      
      if (! $ftp->directoryExists(new Directory("{$this->config['ftp']['path']}/{$directory_base}"))) {
        # Create directory
        $ftp->create(new Directory("{$this->config['ftp']['path']}/{$directory_base}"), [FTP::RECURSIVE => true]);
      }
      
      # Upload the file to the location
      $ftp->upload(new File($file_base), $file_base);

      # Tell the console what we did
      $output->writeln(sprintf(
        "<info>[%s] #%d Uploaded %s</info>",
        date('H:m:s'),
        $i,
        $file_base
      ));
      
      $i++;
    }
  }
  
  private function uploadFiles(OutputInterface $output, $ftp, $files) {
    $i         = 0;
    $filecount = sizeof($files);
    
    foreach($files as $file) {
      $i++;
      
      # Ignore the file?
      if ($this->isIgnoredFile($file) === true) {
        continue;
      }
      
      # Does the file exist?
      if (! is_file($file)) {
        # Whoops, let them know...
        $output->writeln(sprintf(
          "<errpr>[%s] %d/%d Non-existant file %s</error>",
          date('H:m:s'),
          $i,
          $filecount,
          $file
        ));
        
        continue;
      }
      
      $directory_base = pathinfo($file, PATHINFO_DIRNAME);
      if (! $ftp->directoryExists(new Directory("{$this->config['ftp']['path']}/{$directory_base}"))) {
        # Create directory
        $ftp->create(new Directory("{$this->config['ftp']['path']}/{$directory_base}"), [FTP::RECURSIVE => true]);
      }
      
      # Upload the file to the location
      $ftp->upload(new File($file), $file);

      # Tell the console what we did
      $output->writeln(sprintf(
        "<info>[%s] %d/%d Uploaded %s</info>",
        date('H:m:s'),
        $i,
        $filecount,
        $file
      ));
    }
  }
}
