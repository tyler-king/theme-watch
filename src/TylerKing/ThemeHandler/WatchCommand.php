<?php

namespace TylerKing\ThemeHandler;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Filesystem\Filesystem;
use JasonLewis\ResourceWatcher\Tracker;
use JasonLewis\ResourceWatcher\Watcher;
use JasonLewis\ResourceWatcher\Event;
use Touki\FTP\FTP;
use Touki\FTP\Model\File;
use Touki\FTP\Model\Directory;

class WatchCommand extends BaseCommand {
  protected function configure() {
    $this
      ->setName('theme:watch')
      ->setDescription('Watches a theme directory for changes')
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    # Start the FTP connection
    $ftp = $this->startFTP();

    # Setup the watcher
    $watcher = new Watcher(new Tracker, new Filesystem);
    
    # Setup the listener
    $listener = $watcher->watch(getcwd());
    $config   = $this->config;
    
    $output->writeln("<info>Changes will be pushed to:</info> <comment>{$this->config['ftp']['path']}</comment>");
    $output->writeln('');
    
    $listener->anything(function($event, $resource, $path) use($ftp, $config, $output) {
      if ($path{0} == '.') {
        return;
      }
      
      switch($event->getCode()) {
        case Event::RESOURCE_MODIFIED :
        case Event::RESOURCE_CREATED :
          # Get the file base compared to the remote base
          $file_base      = $this->getFileBase($path);
          $directory_base = pathinfo($file_base, PATHINFO_DIRNAME);
        
          # Ignore the file?
          if ($this->isIgnoredFile($file_base) === true) {
            continue;
          }

          if (! $ftp->directoryExists(new Directory("{$config['ftp']['path']}/{$directory_base}"))) {
            # Create directory
            $ftp->create(new Directory("{$config['ftp']['path']}/{$directory_base}"), [FTP::RECURSIVE => true]);
          }

          # Upload the file to the location
          $ftp->upload(new File($file_base), $path);

          # Tell the console what we did
          $output->writeln(sprintf(
            "<info>[%s] Uploaded (%s) %s</info>",
            date('H:m:s'),
            $event->getCode() == Event::RESOURCE_MODIFIED ? 'update' : 'create',
            $file_base
          ));
        
          break;
        case Event::RESOURCE_DELETED :
          $file_base = $this->getFileBase($path);
          $file      = $ftp->findFileByName("{$config['ftp']['path']}/{$file_base}");
        
          # Ignore the file?
          if ($this->isIgnoredFile($file_base) === true || ! $file) {
            continue;
          }

          # Delete the file
          $ftp->delete($file);
        
          # Tell the console what we did
          $output->writeln(sprintf(
            "<comment>[%s] Deleted %s</comment>",
            date('H:m:s'),
            $file_base
          ));
        
          break;
      }
    });
    
    # Start watching
    $watcher->start($this->config['theme']['interval']);
    
    $this->stopFTP();
  }
}
