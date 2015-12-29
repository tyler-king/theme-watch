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
  protected $last_time;
  
  protected function configure() {
    $this
      ->setName('theme:watch')
      ->setDescription('Watches a theme directory for changes')
      ->addOption(
        'timeout-prevent',
        null,
        InputOption::VALUE_OPTIONAL,
        'How often in minutes should we prevent timeout?',
        4
      )
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    # Setup the config
    $this->setupConfig();
    
    # Start the FTP connection
    $this->startFTP();

    # Setup the watcher
    $watcher = new Watcher(new Tracker, new Filesystem);
    
    # Setup the listener
    $listener = $watcher->watch(getcwd());
    $listener->onAnything(function($event, $resource, $path) use($output) {
      $file_base = $this->getFileBase($path);
      if ($this->isIgnoredFile($file_base) === true) {
        return;
      }
      
      switch($event->getCode()) {
        case Event::RESOURCE_MODIFIED :
        case Event::RESOURCE_CREATED :
          $directory_base = pathinfo($file_base, PATHINFO_DIRNAME);

          if ($directory_base != '.' && ! $this->ftp->directoryExists(new Directory("{$this->config['ftp']['path']}/{$directory_base}"))) {
            # Create directories, they dont exist
            $this->ftp->create(new Directory("{$this->config['ftp']['path']}/{$directory_base}"), [FTP::RECURSIVE => true]);
          }

          # Upload the file to the location
          $this->ftp->upload(new File($file_base), $path);

          # Tell the console what we did
          $output->writeln(sprintf(
            "<info>[%s] Uploaded (%s) %s</info>",
            date('H:m:s'),
            $event->getCode() == Event::RESOURCE_MODIFIED ? 'update' : 'create',
            $file_base
          ));
        
          break;
        case Event::RESOURCE_DELETED :
          # Delete the file
          $file = $this->ftp->findFileByName("{$this->config['ftp']['path']}/{$file_base}");
          if ($file) {
            $this->ftp->delete($file);
        
            # Tell the console what we did
            $output->writeln(sprintf(
              "<comment>[%s] Deleted %s</comment>",
              date('H:m:s'),
              $file_base
            ));
          }
        
          break;
      }
    });
    
    $output->writeln(">>> <comment>Changes will be pushed to: {$this->config['ftp']['path']}</comment>");
    $output->writeln('');
    
    # Catch terminates to clean up
    if (function_exists('pcntl_signal')) {
      $cleanup = function() use($output) {
        $this->stopFTP();

        $output->writeln("\n>>> <info>Done</info>");

        exit;
      };
      pcntl_signal(SIGINT, $cleanup);
      pcntl_signal(SIGTERM, $cleanup);
    }

    # Start watching
    $this->last_time = time();
    $watcher->start($this->config['theme']['interval'], null, function() use($output, $input) {
      if (function_exists('pcntl_signal')) { pcntl_signal_dispatch(); }

      # Determine if we need to say hello to the FTP connection again to keep it alive
      if ((time() - $this->last_time) / 60 > $input->getOption('timeout-prevent')) {
        # We need to say hello
        $output->writeln('>>> <comment>Preventing timeout</comment>');
        $this->wrapper->raw('NOOP');
        
        $this->last_time = time();
      }
    });
  }
}
