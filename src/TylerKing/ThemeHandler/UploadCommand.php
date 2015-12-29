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
    # Setup the config
    $this->setupConfig();
    
    # Start the FTP connection
    $this->startFTP();
    
    if (sizeof($input->getArgument('files')) === 0) {
      $this->uploadAllFiles($input, $output);
    } else {
      $this->uploadFiles($input, $output);
    }
    
    $this->stopFTP();
  }
  
  private function uploadAllFiles(InputInterface $input, OutputInterface $output) {
    $i        = 1;
    $files    = new RecursiveDirectoryIterator(getcwd());
    $iterator = new RecursiveIteratorIterator($files);
    
    foreach($iterator as $file) {      
      $file_base      = $this->getFileBase($file->getPathname());
      $directory_base = pathinfo($file_base, PATHINFO_DIRNAME);
      
      # Ignore the file? (and dots)
      if ($this->isIgnoredFile($file_base) === true || ! is_file($file->getPathname())) {
        continue;
      }

      if ($directory_base != '.' && ! $this->ftp->directoryExists(new Directory("{$this->config['ftp']['path']}/{$directory_base}"))) {
        # Create directories, they dont exist
        $this->ftp->create(new Directory("{$this->config['ftp']['path']}/{$directory_base}"), [FTP::RECURSIVE => true]);
      }
      
      # Upload the file to the location
      $this->ftp->upload(new File($file_base), $file_base);

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
  
  private function uploadFiles(InputInterface $input, OutputInterface $output) {
    $i         = 0;
    $files     = $input->getArgument('files');
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
          "<error>[%s] %d/%d No such file %s</error>",
          date('H:m:s'),
          $i,
          $filecount,
          $file
        ));
        
        continue;
      }
      
      $directory_base = pathinfo($file, PATHINFO_DIRNAME);
      if ($directory_base != '.' && ! $this->ftp->directoryExists(new Directory("{$this->config['ftp']['path']}/{$directory_base}"))) {
        # Create directories, they dont exist
        $this->ftp->create(new Directory("{$this->config['ftp']['path']}/{$directory_base}"), [FTP::RECURSIVE => true]);
      }
      
      # Upload the file to the location
      $this->ftp->upload(new File($file), $file);

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
